<?php

namespace App\Http\Controllers;

use App\Enums\EstadoSP;
use App\Http\Requests\SolicitudPago\AutorizarSolicitudPagoGestionPlusRequest;
use App\Http\Requests\SolicitudPago\PagarSolicitudPagoGestionPlusRequest;
use App\Http\Requests\SolicitudPago\RechazarSolicitudPagoGestionPlusRequest;
use App\Http\Resources\SolicitudPago\SolicitudPagoResource;
use App\Models\CuentaBancaria;
use App\Models\PagoSolicitudPago;
use App\Models\PagoSPP;
use App\Models\Proveedor;
use App\Models\SolicitudPago;
use App\Notifications\SolicitudPago\SolicitudPagoAbonadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoPagadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoRechazadaNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProveedorSolicitudPagoRecibidasController extends Controller
{
    /**
     * SPP donde la empresa activa es pagadora (canal GestionPlus).
     */
    public function index(Request $request, Proveedor $proveedor): JsonResponse
    {
        $filters = $request->only(SolicitudPago::getFilters());
        unset($filters['proveedor_id'], $filters['empresa_construcc_id']);
        $filters['empresa_gestionplus_id'] = $proveedor->id;

        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');
        $perPage = (int) $request->input('per_page', 10);

        $baseQuery = SolicitudPago::query()
            ->where('empresa_gestionplus_id', $proveedor->id)
            ->whereNull('empresa_construcc_id');

        if (! empty($filters)) {
            $baseQuery->filter($filters);
        }

        $segmentCounts = (clone $baseQuery)
            ->selectRaw('estado_solicitud, COUNT(*) as total')
            ->groupBy('estado_solicitud')
            ->pluck('total', 'estado_solicitud')
            ->toArray();

        $query = (clone $baseQuery)
            ->with(array_merge(SolicitudPago::eagerLodable(), ['empresaGestionPlus']))
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = SolicitudPagoResource::collection($query)->resolve();

        return $this->paginated(
            $query->setCollection(collect($data)),
            'Solicitudes recibidas (GestionPlus).',
            200,
            [
                'segment_counts' => [
                    'pendiente' => (int) ($segmentCounts[EstadoSP::PENDIENTE->value] ?? 0),
                    'rechazada' => (int) ($segmentCounts[EstadoSP::RECHAZADA->value] ?? 0),
                    'autorizada' => (int) ($segmentCounts[EstadoSP::AUTORIZADA->value] ?? 0),
                    'pagado' => (int) ($segmentCounts[EstadoSP::PAGADO->value] ?? 0),
                ],
            ]
        );
    }

    public function show(Proveedor $proveedor, SolicitudPago $solicitudPago): JsonResponse
    {
        if (! $this->esPagadora($proveedor, $solicitudPago)) {
            return $this->error('La solicitud no pertenece a esta empresa como pagadora.', null, 403);
        }

        $solicitudPago->load(array_merge(SolicitudPago::eagerLodable(), ['empresaGestionPlus']));

        if ($requestUser = auth()->user()) {
            $solicitudPago->markRead($requestUser);
        }

        return $this->success(
            new SolicitudPagoResource($solicitudPago),
            'Detalle de solicitud recibida.'
        );
    }

    public function autorizar(
        AutorizarSolicitudPagoGestionPlusRequest $request,
        Proveedor $proveedor,
        SolicitudPago $solicitudPago
    ): JsonResponse {
        if (! $this->esPagadora($proveedor, $solicitudPago)) {
            return $this->error('La solicitud no pertenece a esta empresa como pagadora.', null, 403);
        }

        if ($solicitudPago->estado_solicitud !== EstadoSP::PENDIENTE->value) {
            return $this->error(
                'Solo se pueden autorizar solicitudes en estado pendiente.',
                ['estado' => $solicitudPago->estado_solicitud],
                422
            );
        }

        $data = $request->validated();
        $montoAutorizado = isset($data['monto_autorizado'])
            ? (float) $data['monto_autorizado']
            : (float) $solicitudPago->monto_total;

        if ($montoAutorizado > (float) $solicitudPago->monto_total) {
            return $this->error(
                'El monto autorizado no puede ser mayor al monto total.',
                [
                    'monto_total' => (float) $solicitudPago->monto_total,
                    'monto_autorizado' => $montoAutorizado,
                ],
                422
            );
        }

        $user = $request->user();

        $payload = [
            'estado_solicitud' => EstadoSP::AUTORIZADA->value,
            'fecha_aprobado' => now(),
        ];

        if ($montoAutorizado < (float) $solicitudPago->monto_total) {
            $payload['monto_autorizado'] = $montoAutorizado;
            $payload['usuario_autorizo_parcial_id'] = $user?->id;
            $payload['usuario_autorizo_parcial_nombre'] = $user?->name;
            $payload['motivo_autorizacion_parcial'] = $data['notas'] ?? 'Autorización parcial GestionPlus';
            $payload['fecha_autorizacion_parcial'] = now();
        }

        $solicitudPago->update($payload);

        return $this->success(
            new SolicitudPagoResource(
                $solicitudPago->fresh()->load(array_merge(SolicitudPago::eagerLodable(), ['empresaGestionPlus']))
            ),
            'Solicitud autorizada correctamente.'
        );
    }

    public function rechazar(
        RechazarSolicitudPagoGestionPlusRequest $request,
        Proveedor $proveedor,
        SolicitudPago $solicitudPago
    ): JsonResponse {
        if (! $this->esPagadora($proveedor, $solicitudPago)) {
            return $this->error('La solicitud no pertenece a esta empresa como pagadora.', null, 403);
        }

        if ($solicitudPago->estado_solicitud === EstadoSP::RECHAZADA->value) {
            return $this->success(
                new SolicitudPagoResource($solicitudPago),
                'La solicitud ya está rechazada.'
            );
        }

        if ($solicitudPago->estado_solicitud === EstadoSP::PAGADO->value) {
            return $this->error('No se pueden rechazar solicitudes pagadas.', null, 422);
        }

        $motivo = $request->validated()['motivo_rechazo'];

        $solicitudPago->update([
            'estado_solicitud' => EstadoSP::RECHAZADA->value,
            'motivo_rechazo' => $motivo,
            'fecha_rechazo' => now(),
        ]);

        $this->notificarCobradoraRechazo($solicitudPago, $motivo);

        return $this->success(
            new SolicitudPagoResource(
                $solicitudPago->fresh()->load(array_merge(SolicitudPago::eagerLodable(), ['empresaGestionPlus']))
            ),
            'Solicitud rechazada correctamente.'
        );
    }

    public function pagar(
        PagarSolicitudPagoGestionPlusRequest $request,
        Proveedor $proveedor,
        SolicitudPago $solicitudPago
    ): JsonResponse {
        if (! $this->esPagadora($proveedor, $solicitudPago)) {
            return $this->error('La solicitud no pertenece a esta empresa como pagadora.', null, 403);
        }

        if ($solicitudPago->estado_solicitud !== EstadoSP::AUTORIZADA->value) {
            return $this->error(
                'Solo se pueden pagar solicitudes autorizadas.',
                ['estado' => $solicitudPago->estado_solicitud],
                422
            );
        }

        $validated = $request->validated();
        $montoPago = round((float) $validated['monto_pago'], 2);
        $saldoDisponible = round($solicitudPago->calcularSaldoRestante(), 2);

        if ($montoPago > $saldoDisponible) {
            return $this->error(
                'El monto excede el saldo disponible.',
                [
                    'saldo_disponible' => $saldoDisponible,
                    'monto_pago' => $montoPago,
                ],
                422
            );
        }

        if (! empty($validated['idempotency_key'])) {
            $existente = PagoSPP::query()
                ->where('empresa_gestionplus_id', $proveedor->id)
                ->where('proveedor_id', $solicitudPago->proveedor_id)
                ->where('observaciones', 'idem:' . $validated['idempotency_key'])
                ->first();

            if ($existente) {
                return $this->success(
                    [
                        'pago_id' => $existente->id,
                        'solicitud' => new SolicitudPagoResource(
                            $solicitudPago->fresh()->load(array_merge(SolicitudPago::eagerLodable(), ['empresaGestionPlus']))
                        ),
                    ],
                    'Pago ya registrado (idempotente).'
                );
            }
        }

        $user = $request->user();

        try {
            DB::connection('mysql5')->beginTransaction();

            $comprobantePath = $request->file('comprobante_pago')->store('comprobantes', 'private');

            $ultimos4 = null;
            if (! empty($validated['cuenta_destino_id'])) {
                $cuenta = CuentaBancaria::findOrFail($validated['cuenta_destino_id']);
                if ((int) $cuenta->proveedor_id !== (int) $solicitudPago->proveedor_id) {
                    DB::connection('mysql5')->rollBack();

                    return $this->error('La cuenta destino no pertenece al proveedor cobrador.', null, 422);
                }
                $numeroPago = $cuenta->obtenerNumeroPago();
                $campo = preg_replace('/\D+/', '', (string) $numeroPago);
                $ultimos4 = substr($campo, -4);
            }

            $observaciones = $validated['observaciones'] ?? null;
            if (! empty($validated['idempotency_key'])) {
                $observaciones = trim(($observaciones ? $observaciones . ' | ' : '') . 'idem:' . $validated['idempotency_key']);
            }

            $pago = PagoSPP::create([
                'origen' => PagoSPP::ORIGEN_SPP,
                'comprobante_pago' => $comprobantePath,
                'empresa_construcc_id' => null,
                'empresa_gestionplus_id' => $proveedor->id,
                'proveedor_id' => $solicitudPago->proveedor_id,
                'usuario_registro_id' => $user?->id,
                'usuario_registro_nombre' => $user?->name,
                'cuenta_destino_id' => $validated['cuenta_destino_id'] ?? null,
                'cuenta_destino_terminacion' => $ultimos4,
                'monto_total' => $montoPago,
                'fecha_pago' => $validated['fecha_pago'] ?? now(),
                'referencia_pago' => $validated['referencia_pago'] ?? '',
                'banco_destino' => $validated['banco_destino'] ?? '',
                'titular_cuenta_destino' => $validated['titular_cuenta_destino'] ?? '',
                'clave_rastreo' => $validated['clave_rastreo'] ?? '',
                'fecha_registro' => now(),
                'observaciones' => $observaciones,
            ]);

            $saldoInicial = $saldoDisponible;

            $pago->solicitudesPago()->attach($solicitudPago->id, [
                'saldo_inicial' => $saldoInicial,
                'monto_aplicado' => $montoPago,
                'estado_pago' => $montoPago >= $saldoDisponible
                    ? PagoSolicitudPago::ESTADO_COMPLETADO
                    : PagoSolicitudPago::ESTADO_PARCIAL,
                'fecha_aplicacion' => now(),
            ]);

            $pagoCompleto = $solicitudPago->actualizarSaldos($montoPago);

            // Canal plataforma: tras abono parcial permanece autorizada (no vuelve a pendiente).
            if (! $pagoCompleto && $solicitudPago->estado_solicitud === EstadoSP::PENDIENTE->value) {
                $solicitudPago->update(['estado_solicitud' => EstadoSP::AUTORIZADA->value]);
            }

            $solicitudPago->sincronizarComprobanteDesdePago($comprobantePath, $pago->fecha_pago);
            $solicitudPago->enviarCorreoComprobantePagoAProveedor($comprobantePath);

            DB::connection('mysql5')->commit();

            $this->notificarCobradoraPago($solicitudPago->fresh(), $montoPago, $pagoCompleto);

            return $this->success(
                [
                    'pago_id' => $pago->id,
                    'pago_completo' => $pagoCompleto,
                    'solicitud' => new SolicitudPagoResource(
                        $solicitudPago->fresh()->load(array_merge(SolicitudPago::eagerLodable(), ['empresaGestionPlus']))
                    ),
                ],
                $pagoCompleto ? 'Pago completo registrado.' : 'Abono registrado correctamente.',
                201
            );
        } catch (\Throwable $e) {
            DB::connection('mysql5')->rollBack();
            Log::error('GestionPlus pagar SPP: fallo', [
                'solicitud_id' => $solicitudPago->id,
                'empresa_gestionplus_id' => $proveedor->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function esPagadora(Proveedor $proveedor, SolicitudPago $solicitudPago): bool
    {
        return $solicitudPago->esCanalGestionPlus()
            && (int) $solicitudPago->empresa_gestionplus_id === (int) $proveedor->id;
    }

    private function notificarCobradoraRechazo(SolicitudPago $solicitudPago, string $motivo): void
    {
        $solicitudPago->loadMissing('proveedor');
        $usuarios = $solicitudPago->proveedor?->usuariosActivos()->get() ?? collect();

        foreach ($usuarios->unique('id') as $user) {
            $user->notify(new SolicitudPagoRechazadaNotification(
                (string) $solicitudPago->numero_folio_solicitud,
                (int) $solicitudPago->id,
                (int) $solicitudPago->proveedor_id,
                $motivo,
                (int) $user->id
            ));
        }
    }

    private function notificarCobradoraPago(SolicitudPago $solicitudPago, float $monto, bool $completo): void
    {
        $solicitudPago->loadMissing('proveedor');
        $usuarios = $solicitudPago->proveedor?->usuariosActivos()->get() ?? collect();
        $saldoRestante = (float) $solicitudPago->calcularSaldoRestante();

        foreach ($usuarios->unique('id') as $user) {
            if ($completo) {
                $user->notify(new SolicitudPagoPagadaNotification(
                    (string) $solicitudPago->numero_folio_solicitud,
                    (int) $solicitudPago->id,
                    (int) $solicitudPago->proveedor_id,
                    $monto,
                    (int) $user->id
                ));
            } else {
                $user->notify(new SolicitudPagoAbonadaNotification(
                    (string) $solicitudPago->numero_folio_solicitud,
                    (int) $solicitudPago->id,
                    (int) $solicitudPago->proveedor_id,
                    $monto,
                    $saldoRestante,
                    (int) $user->id
                ));
            }
        }
    }
}
