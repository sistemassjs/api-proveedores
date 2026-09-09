<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Enums\EstadoSP;

use App\Http\Requests\Construcc\ConstruccPagosSPPRegistrarPagoRequest;
use App\Http\Resources\Construcc\ConstruccPagoIndexResource;
use App\Http\Resources\Construcc\ConstruccPagoProveedorResource;
use App\Http\Resources\Construcc\ConstruccPagoResource;
use App\Http\Resources\Construcc\ConstruccPagoResumenResource;
use App\Http\Resources\Construcc\ConstruccPagoSPPResource;
use App\Models\CuentaBancaria;
use App\Models\EmpresaConstrucc;
use App\Models\PagoSPP;
use App\Models\Proveedor;
use App\Models\SolicitudPago;
use App\Models\PagoSolicitudPago;
use App\Notifications\SolicitudPago\SolicitudPagoAbonadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoComprobanteActualizadoNotification;
use App\Notifications\SolicitudPago\SolicitudPagoFacturaPendienteNotification;
use App\Notifications\SolicitudPago\SolicitudPagoPagadaNotification;
use App\Support\PublicStorageUrl;
use App\Services\InterApiService;
use Carbon\Carbon;

/**
 * Controlador para gestionar los pagos de solicitudes de pago (SPP).
 * Maneja la relación muchos a muchos entre pagos y solicitudes de pago.
 */
class ConstruccPagosSPPController extends Controller
{

    protected $interApiService;

    public function __construct(InterApiService $interApiService)
    {
        $this->interApiService = $interApiService;
    }


    /**
     * Lista de pagos con filtros y paginación.
     *
     * GET /api/construcc/pagos-spp/
     * Parámetros adicionales:
     * - usuario_nivel: si es 6 (Residente de Obra), restringe el listado.
     * - usuario_id: id de usuario construcc; con usuario_nivel=6 solo se listan pagos
     *   que aplican a al menos una SPP creada por ese usuario (solicitudes_pago.usuario_id).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(PagoSPP::getFilters());
            $sortBy  = $request->input('sort_by', 'fecha_pago');
            $order   = $request->input('order', 'desc');
            $perPage = $request->input('per_page', 10000);

            $query = PagoSPP::query()
                ->with([
                    'proveedor',
                    'empresaConstrucc',
                    // Opcional: si quieres ver las solicitudes relacionadas en el index
                    'solicitudesPago',
                ])
                ->withCount('solicitudesPago') // 👈 agrega el conteo
                ->filter($filters)
                ->orderBy($sortBy, $order);

            $paginator = $query->paginate($perPage);

            return $this->paginated(
                $paginator->setCollection(
                    ConstruccPagoIndexResource::collection($paginator)->collection
                ),
                'Pagos SPP obtenidos exitosamente.'
            );
        } catch (\Throwable $e) {
            Log::error('Error al listar pagos SPP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                'No se pudieron obtener los pagos SPP. Por favor, intente nuevamente.',
                null,
                500
            );
        }
    }

    /**
     * Mostrar un pago
     * 
     * GET /api/construcc/pagos-spp/{pago}
     */
    public function show(PagoSPP $pago): JsonResponse
    {
        $pago->load([
            'empresaConstrucc',
            'proveedor',
            'solicitudesPago', // esta relación ya tiene pivot + orden definido
        ]);

        return $this->success([
            'pago' => new ConstruccPagoResource($pago),
        ], 'Pago obtenido correctamente.');
    }

    /**
     * listado de proveedores con informacion de las SPP activas.
     * El listado se realiza en base a las SPP autorizadas y se agrupan por proveedor.
     * 
     *  GET /api/construcc/pagos-spp/proveedores?empresas_construcc={id_empresa_construcc}
     */
    public function indexProveedor(Request $request): JsonResponse
    {
        try {
            $empresaConstruccId = $request->integer('empresa_construcc_id');
            $perPage = $request->input('per_page', 1000);

            $query = SolicitudPago::query()
                ->selectRaw('proveedor_id, COUNT(*) as spp_autorizadas_count')
                ->where('estado_solicitud', 'autorizada')
                ->when($empresaConstruccId, function ($q) use ($empresaConstruccId) {
                    $q->where('empresa_construcc_id', $empresaConstruccId);
                })
                ->groupBy('proveedor_id')
                ->with([
                    'proveedor:id,nombre_comercial',
                    'proveedor.cuentasBancarias',
                ]);

            $paginator = $query->paginate($perPage);

            // 🔥 Adaptamos la colección para que el resource reciba el proveedor
            $paginator->getCollection()->transform(function ($row) {
                /** @var Proveedor */
                $proveedor = $row->proveedor;
                $proveedor->spp_autorizadas_count = $row->spp_autorizadas_count;
                return $proveedor;
            });


            $data = ConstruccPagoProveedorResource::collection($paginator)->resolve();

            return $this->paginated(
                $paginator->setCollection(collect($data))
            );
        } catch (\Exception $e) {
            Log::error('Error al listar proveedores con SPP autorizadas', [
                'error' => $e->getMessage(),
            ]);

            return $this->error(
                'No se pudieron obtener los proveedores.',
                null,
                500
            );
        }
    }

    /**
     * Lista todas las SPP de un proveedor específico con filtros y paginación.
     *
     * GET /api/construcc/pagos-spp/proveedor/{proveedor}/spp?empresas_construcc={id_empresa_construcc}
     */
    public function sppPorProveedor(Request $request, Proveedor $proveedor): JsonResponse
    {
        try {
            $empresaConstruccId = $request->integer('empresa_construcc_id');
            $perPage = $request->input('per_page', 1000);

            $query = SolicitudPago::query()
                ->where('proveedor_id', $proveedor->id)
                ->where('estado_solicitud', 'autorizada')
                ->when($empresaConstruccId, function ($q) use ($empresaConstruccId) {
                    $q->where('empresa_construcc_id', $empresaConstruccId);
                })
                ->withSum('pagos as total_pagado', 'pago_solicitud_pago.monto_aplicado')
                ->with(array_merge(
                    SolicitudPago::eagerLodable(),
                    [
                        'pagos' => function ($q) {
                            $q->orderBy('pago_solicitud_pago.fecha_aplicacion', 'desc');
                        }
                    ]
                ));

            $paginator = $query->paginate($perPage);

            return $this->paginated(
                $paginator->setCollection(ConstruccPagoSPPResource::collection($paginator)->collection),
                'Solicitudes de pago obtenidas exitosamente.'
            );
        } catch (\Exception $e) {
            Log::error('Error al listar SPP del proveedor', [
                'proveedor_id' => $proveedor->id ?? null,
                'error'        => $e->getMessage(),
            ]);

            return $this->error(
                'No se pudieron obtener las solicitudes de pago.',
                null,
                500
            );
        }
    }

    /**
     * Muestra una SPP específica de un proveedor con todos sus pagos parciales.
     * 
     * GET /api/construcc/pagos-spp/proveedor/{proveedor}/spp/{spp}
     */
    public function showSppProveedor(Request $request, Proveedor $proveedor, SolicitudPago $spp): JsonResponse
    {
        try {
            // 🔐 Validación de pertenencia
            if ($spp->proveedor_id !== $proveedor->id) {
                return $this->error(
                    'La solicitud de pago no pertenece a este proveedor.',
                    null,
                    403
                );
            }

            // 📦 Cargar relaciones necesarias para el resource
            $spp->load([
                'proveedor',
                'empresaConstrucc',
                'cuentasBancarias',
                'pagos' => function ($query) {
                    $query->with([
                        'empresaConstrucc',
                        'proveedor',
                    ])
                        ->withPivot([
                            'solicitud_pago_id',
                            'monto_aplicado',
                            'fecha_aplicacion',
                        ])
                        ->orderByPivot('fecha_aplicacion', 'desc');
                },
            ]);

            // ✅ Respuesta con resource específico de SPP (que ya incluye pagos)
            return $this->success(
                [
                    'solicitud_pago' => new ConstruccPagoSPPResource($spp),
                    'pagos' => ConstruccPagoResource::collection($spp->pagos),
                ],
                'Solicitud de pago obtenida exitosamente.'
            );
        } catch (\Throwable $e) {
            Log::error('Error al obtener SPP del proveedor', [
                'proveedor_id' => $proveedor->id,
                'spp_id'       => $spp->id,
                'error'        => $e->getMessage(),
            ]);

            return $this->error(
                'No se pudo obtener el detalle de la solicitud de pago. Por favor, intente nuevamente.',
                null,
                500
            );
        }
    }


    /**
     * Lista todos los pagos parciales de una SPP específica.
     * 
     * GET /api/construcc/pagos-spp/proveedor/{proveedor}/spp/{spp}/pagos
     */
    public function pagosDeSpp(Proveedor $proveedor, SolicitudPago $spp): JsonResponse
    {
        try {
            // Verificar que la SPP pertenece al proveedor
            if ($spp->proveedor_id !== $proveedor->id) {
                return $this->error(
                    'La solicitud de pago no pertenece a este proveedor.',
                    null,
                    403
                );
            }

            $pagos = $spp->pagos()
                ->with([
                    'empresaConstrucc',
                    'proveedor', // 👈 NECESARIO para armar la URL
                ])
                ->withPivot([
                    'solicitud_pago_id', // 👈 NECESARIO para el parámetro {spp}
                    'monto_aplicado',
                    'estado_pago',
                    'notas',
                    'fecha_aplicacion'
                ])
                ->orderBy('pago_solicitud_pago.fecha_aplicacion', 'desc')
                ->get();

            return $this->success(ConstruccPagoResumenResource::collection($pagos), 'Pagos obtenidos exitosamente.');
        } catch (\Throwable $e) {
            Log::error('Error al listar pagos de SPP', [
                'proveedor_id' => $proveedor->id,
                'spp_id' => $spp->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error(
                'No se pudieron obtener los pagos. Por favor, intente nuevamente.',
                null,
                500
            );
        }
    }

    /**
     * Lista todas las SPP asociadas a un pago específico de un proveedor.
     * 
     * GET /api/construcc/pagos-spp/proveedor/{proveedor}/pagos/{pago}/spps
     */
    public function sppDePago(Proveedor $proveedor, PagoSPP $pago): JsonResponse
    {
        try {
            // Obtener solo las SPP del proveedor
            $spps = $pago->solicitudesPago()
                ->where('proveedor_id', $proveedor->id)
                ->with(['empresaConstrucc', 'proveedor'])
                ->withPivot([
                    'monto_aplicado',
                    'estado_pago',
                    'notas',
                    'fecha_aplicacion'
                ])
                ->orderBy('pago_solicitud_pago.fecha_aplicacion', 'desc')
                ->get();

            return $this->success([
                'pago' => ConstruccPagoResource::make($pago),
                'solicitudes_pago' => ConstruccPagoSPPResource::collection($spps),
            ], 'Solicitudes de pago obtenidas exitosamente.');
        } catch (\Throwable $e) {
            Log::error('Error al listar SPP de un pago', [
                'proveedor_id' => $proveedor->id,
                'pago_id' => $pago->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error(
                'No se pudieron obtener las solicitudes de pago del pago.',
                null,
                500
            );
        }
    }

    /**
     * Sube o actualiza el comprobante de pago para un pago específico.
     * 
     * POST /api/construcc/pagos-spp/proveedor/{proveedor}/spp/{spp}/pagos/{pago}/subir-comprobante
     */
    public function subirComprobanteSpp(Request $request, Proveedor $proveedor, SolicitudPago $spp, PagoSPP $pago): JsonResponse
    {
        try {
            // Verificar que la SPP pertenece al proveedor
            if ($spp->proveedor_id !== $proveedor->id) {
                return $this->error(
                    'La solicitud de pago no pertenece a este proveedor.',
                    null,
                    403
                );
            }

            // Verificar que el pago está asociado a esta SPP
            $pagoAsociado = $pago->solicitudesPago()
                ->where('solicitud_pago_id', $spp->id)
                ->exists();

            if (!$pagoAsociado) {
                return $this->error(
                    'El pago no está asociado a esta solicitud de pago.',
                    null,
                    404
                );
            }

            // Validación
            $validated = $request->validate([
                'comprobante_pago' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ], [
                'comprobante_pago.required' => 'El comprobante de pago es obligatorio.',
                'comprobante_pago.file' => 'El comprobante debe ser un archivo válido.',
                'comprobante_pago.mimes' => 'El comprobante debe ser PDF, JPG o PNG.',
                'comprobante_pago.max' => 'El comprobante no debe superar los 10MB.',
            ]);

            DB::beginTransaction();

            // Eliminar el comprobante anterior si existe
            if ($pago->comprobante_pago && Storage::disk('public')->exists($pago->comprobante_pago)) {
                Storage::disk('public')->delete($pago->comprobante_pago);
            }

            // Guardar el nuevo comprobante
            $comprobantePath = $request->file('comprobante_pago')->store(
                'comprobantes_pago',
                'public'
            );

            $pago->update([
                'comprobante_pago' => $comprobantePath,
            ]);

            $proveedor->notify(
                new SolicitudPagoComprobanteActualizadoNotification(
                    $spp->numero_folio_solicitud,
                    $spp->id,
                    $proveedor->id,
                    null,
                    $comprobantePath,
                    'public'
                )
            );

            DB::commit();

            return $this->success([
                'comprobante_pago' => $comprobantePath,
                'comprobante_pago_url' => PublicStorageUrl::make($comprobantePath),
            ], 'Comprobante de pago guardado exitosamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return $this->error(
                'Error de validación en el archivo.',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al subir comprobante de pago', [
                'proveedor_id' => $proveedor->id,
                'spp_id' => $spp->id,
                'pago_id' => $pago->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('No se pudo guardar el comprobante. Por favor, intente nuevamente.', null, 500);
        }
    }

    /**
     * Registra un pago y lo aplica a una o varias SPP de un proveedor.
     * Este endpoint permite crear un pago con comprobante y asociarlo a múltiples SPP.
     * 
     * POST /api/construcc/pagos-spp/proveedor/{proveedor}/pagos?empresas_construcc={id_empresa_construcc}
     */
    public function registrarPagoProveedor(
        ConstruccPagosSPPRegistrarPagoRequest $request,
        Proveedor $proveedor
    ): JsonResponse {
        try {
            $validated = $request->validated();

            Log::info('PAGO: Iniciando registro de pago SPP', ['proveedor_id' => $proveedor->id, 'empresa_construcc_id' => $validated['empresa_id'] ?? null, 'usuario_id' => $validated['usuario_id'], 'cantidad_spp' => count($validated['solicitudes']), 'monto_total_pago' => $validated['monto_total'],]);

            /** @var User  */
            $proveedorUsuarioPrincipal = $proveedor->usuarioPrincipal();
            $empresaConstruccId = $validated['empresa_id'];

            if ((int) $validated['proveedor_id'] !== (int) $proveedor->id) {
                return $this->error(
                    'El proveedor del body no coincide con el proveedor de la ruta.',
                    ['proveedor_id' => (int) $validated['proveedor_id'], 'proveedor_ruta' => (int) $proveedor->id],
                    422
                );
            }

            $proveedorPerteneceEmpresa = $proveedor->empresasConstrucc()
                ->where('empresa_construcc.id', $empresaConstruccId)
                ->exists()
                || ((int) ($proveedor->empresa_construcc_alta ?? 0) === (int) $empresaConstruccId);

            if (! $proveedorPerteneceEmpresa) {
                return $this->error(
                    'El proveedor no pertenece a la empresa indicada.',
                    ['proveedor_id' => (int) $proveedor->id, 'empresa_id' => (int) $empresaConstruccId],
                    422
                );
            }

            $listSPPIds = collect($validated['solicitudes'])
                ->pluck('solicitud_id')
                ->unique()
                ->values();

            Log::info('PAGO: Antes de validar SPPs para el pago', [
                'list_spp_ids' => $listSPPIds,
            ]);

            $solicitudes = SolicitudPago::whereIn('id', $listSPPIds)
                ->get()
                ->keyBy('id');

            Log::info('PAGO: Despues de validar SPPs para el pago', [
                'list_spp_ids' => $solicitudes->pluck('id'),
            ]);

            $erroresSolicitudes = [];

            foreach ($listSPPIds as $solicitudId) {
                $solicitud = $solicitudes->get($solicitudId);

                if (! $solicitud) {
                    $erroresSolicitudes[(int) $solicitudId] = ['La solicitud de pago no existe.'];
                    continue;
                }

                $erroresPorSolicitud = [];

                if ((int) $solicitud->proveedor_id !== (int) $proveedor->id) {
                    $erroresPorSolicitud[] = 'La solicitud no pertenece al proveedor.';
                }

                if ((int) $solicitud->empresa_construcc_id !== (int) $empresaConstruccId) {
                    $erroresPorSolicitud[] = 'La solicitud no pertenece a la empresa indicada.';
                }

                if ((string) $solicitud->estado_solicitud === EstadoSP::PAGADO->value) {
                    $erroresPorSolicitud[] = 'La solicitud ya se encuentra liquidada.';
                }

                if ((string) $solicitud->estado_solicitud !== EstadoSP::AUTORIZADA->value && (string) $solicitud->estado_solicitud !== EstadoSP::PAGADO->value) {
                    $erroresPorSolicitud[] = 'La solicitud no esta en estado autorizada.';
                }


                if (! empty($erroresPorSolicitud)) {
                    $erroresSolicitudes[(int) $solicitudId] = $erroresPorSolicitud;
                }
            }

            if (! empty($erroresSolicitudes)) {
                return $this->error(
                    'Existen solicitudes con errores de relacion/estado.',
                    ['solicitudes' => $erroresSolicitudes],
                    422
                );
            }

            // Log::info('PAGO: Despues de validar SPPs para el pago', [
            //     'list_spp_ids' => $listSPPIds,
            // ]);

            // Una sola consulta para saldos restantes (evita N+1 de calcularSaldoRestante() en el loop)
            $totalesAplicadosPorSpp = PagoSolicitudPago::query()
                ->whereIn('solicitud_pago_id', $listSPPIds)
                ->whereIn('estado_pago', [
                    PagoSolicitudPago::ESTADO_APLICADO,
                    PagoSolicitudPago::ESTADO_COMPLETADO,
                    PagoSolicitudPago::ESTADO_PARCIAL,
                ])
                ->selectRaw('solicitud_pago_id, COALESCE(SUM(monto_aplicado), 0) as total_aplicado')
                ->groupBy('solicitud_pago_id')
                ->pluck('total_aplicado', 'solicitud_pago_id')
                ->map(fn($v) => (float) $v);

            $montoSolicitadoPorSpp = collect($validated['solicitudes'])
                ->groupBy('solicitud_id')
                ->map(fn($items) => (float) $items->sum(fn($item) => (float) $item['monto_pago']));

            foreach ($montoSolicitadoPorSpp as $solicitudId => $montoSolicitado) {
                $solicitudPago = $solicitudes->get($solicitudId);
                $totalAplicadoPrevio = $totalesAplicadosPorSpp->get($solicitudId, 0.0);
                $saldoDisponible = max(0, (float) $solicitudPago->monto_total - $totalAplicadoPrevio);

                if (round($montoSolicitado, 2) > round($saldoDisponible, 2)) {
                    return $this->error(
                        "La SPP {$solicitudId} excede el saldo disponible.",
                        [
                            'solicitud_id' => (int) $solicitudId,
                            'saldo_disponible' => round($saldoDisponible, 2),
                            'monto_solicitado' => round($montoSolicitado, 2),
                        ],
                        422
                    );
                }
            }

            $montoTotalPago = (float) ($validated['info_comprobante']['monto'] ?? $validated['monto_total'] ?? 0);
            $sumaMontoSPPs = (float) collect($validated['solicitudes'])->sum(function ($item) {
                return (float) $item['monto_pago'];
            });

            if (round($sumaMontoSPPs, 2) !== round($montoTotalPago, 2)) {
                return $this->error(
                    'El monto del comprobante no coincide con el monto total aplicado a las solicitudes.',
                    ['monto_total' => $montoTotalPago, 'monto_aplicado' => $sumaMontoSPPs,],
                    422
                );
            }

            $notificaciones = [];

            // Log::info('PAGO: Begin Transaction', [
            //     'proveedor_id' => $proveedor->id,
            //     'empresa_construcc_id' => $empresaConstruccId,
            //     'usuario_id' => $validated['usuario_id'],
            //     'cantidad_spp' => count($validated['solicitudes']),
            //     'monto_total_pago' => $montoTotalPago,
            //     'suma_monto_spps' => $sumaMontoSPPs,
            // ]);
            DB::beginTransaction();

            $file = $request->file('comprobante_pago');
            $comprobantePath = $file->store('comprobantes', 'private');

            $infoComprobante = $validated['info_comprobante'] ?? [];

            $folio_consecutivo_construcc = null;
            if ($empresaConstruccId) {
                $empresaConstrucc = EmpresaConstrucc::find($empresaConstruccId);
                if ($empresaConstrucc) {
                    $folio_consecutivo_construcc = $empresaConstrucc->obtenerFolioSiguientePagoSPP();
                }
            }

            $cuentaBancaria = CuentaBancaria::findOrFail($validated['cuenta_destino_id']);
            $numeroPago = $cuentaBancaria->obtenerNumeroPago();
            $campo = preg_replace('/\D+/', '', (string) $numeroPago);
            $ultimos4 = substr($campo, -4);

            $pago = PagoSPP::create([
                'comprobante_pago' => $comprobantePath,
                'cuenta_bancaria_empresa_construcc_id' => $validated['cuenta_bancaria_empresa_construcc_id'] ?? null,
                'cuenta_destino_id' => $validated['cuenta_destino_id'] ?? null,
                'cuenta_destino_terminacion' => $ultimos4,
                'empresa_construcc_id' => $validated['empresa_id'],
                'folio_pago_spp_consecutivo' => $folio_consecutivo_construcc,
                'proveedor_id' => $validated['proveedor_id'],
                'usuario_registro_id' => $validated['usuario_id'],
                'usuario_registro_nombre' => $validated['usuario_nombre'],
                'monto_total' => $validated['monto_total'],
                'fecha_pago' => Carbon::parse(
                    trim($validated['info_comprobante']['fecha']) . ' ' .
                        trim($validated['info_comprobante']['hora'])
                )->format('Y-m-d H:i:s'),
                'referencia_pago' => $validated['info_comprobante']['referencia'] ?? '',
                'banco_destino' => $infoComprobante['bancoDestino'] ?? '',
                'titular_cuenta_destino' => $infoComprobante['nombreBeneficiario'] ?? '',
                'clave_rastreo' => $infoComprobante['claveRastreo'] ?? '',
                'fecha_registro' => now(),
            ]);

            // Log::info('PAGO: Create PagoSPP', ['pago' => $pago->id]);

            foreach ($validated['solicitudes'] as $solicitudData) {

                // obtener la SPP ya cargada antes de la transacción para reducir consultas dentro del loop
                /** @var SolicitudPago */
                $solicitudPago = $solicitudes[$solicitudData['solicitud_id']];
                $totalAplicadoPrevio = $totalesAplicadosPorSpp->get($solicitudPago->id, 0.0);
                $saldo_inicial_spp = max(0, (float) $solicitudPago->monto_total - $totalAplicadoPrevio);

                $pago->solicitudesPago()->attach($solicitudPago->id, [
                    'saldo_inicial' => $saldo_inicial_spp,
                    'monto_aplicado' => $solicitudData['monto_pago'],
                    'fecha_aplicacion' => now(),


                    // DATOS DE QUIEN AUTOPRIZO EL PAGO (en caso de que el usuario que registra el pago sea diferente al que autorizó la SPP)
                    'usuario_autorizo_id' => $validated['usuario_autorizo_id'] ?? null,
                    'usuario_autorizo_nombre' => $validated['usuario_autorizo_nombre'] ?? null,
                    'monto_autorizado' => $validated['monto_autorizado'] ?? null,
                    'motivo_autorizacion' => $validated['motivo_autorizacion'] ?? null,
                    'fecha_autorizacion' => $validated['fecha_autorizacion'] ?? null,
                ]);

                $spPagoCompleto = $solicitudPago->actualizarSaldos($solicitudData['monto_pago']);
                $solicitudPago->enviarCorreoComprobantePagoAProveedor($comprobantePath);

                $saldoRestante = (float) $solicitudPago->saldo_pendiente;
                $montoAcumulado = (float) $solicitudPago->monto_abonado;

                if ($proveedorUsuarioPrincipal) {
                    if ($spPagoCompleto) {
                        // Log::info('PAGO: Noticacion Pago Completo', [
                        //     'usuario' => $proveedorUsuarioPrincipal->id,
                        //     'solicitud_pago_id' => $solicitudPago->id,
                        //     'pago_id' => $pago->id,
                        // ]);
                        $notificaciones[] = [
                            'tipo' => 'pagada',
                            'data' => [
                                $solicitudPago->numero_folio_solicitud,
                                $solicitudPago->id,
                                $proveedor->id,
                                $solicitudData['monto_pago'],
                                $proveedorUsuarioPrincipal->id
                            ]
                        ];
                    } else {
                        // Log::info('PAGO: Noticacion Abono', [
                        //     'usuario' => $proveedorUsuarioPrincipal->id,
                        //     'solicitud_pago_id' => $solicitudPago->id,
                        //     'pago_id' => $pago->id,
                        // ]);
                        $notificaciones[] = [
                            'tipo' => 'abonada',
                            'data' => [
                                $solicitudPago->numero_folio_solicitud,
                                $solicitudPago->id,
                                $proveedor->id,
                                $solicitudData['monto_pago'],
                                $saldoRestante,
                                $proveedorUsuarioPrincipal->id,
                                $montoAcumulado,
                                $saldo_inicial_spp
                            ]
                        ];
                    }
                } else if ($proveedor->tipo_alta == 2) {

                    /**
                     * Notificacion para usuario principal del proveedor no encontrada, se omite notificación de pago/abono.
                     */
                    $titulo = $spPagoCompleto ? 'SPP pagada' : 'SPP abonada';
                    $notificaciones[] = [
                        'tipo' => 'pagada_user_construcc',
                        'data'  => [
                            $solicitudPago->id,
                            $solicitudPago->folio_sp_consecutivo,
                            $solicitudPago->empresa_construcc_id,
                            // $validated['folio_factura'],
                            $proveedor->nombre_comercial,
                            $solicitudData['monto_pago'],
                            $pago->fecha_pago,
                            $proveedor->user_construcc_alta,
                            $titulo
                        ]
                    ];
                }

                $uso  = $solicitudData['uso'] ?? null;
                $mp   = $solicitudData['mp'] ?? null;
                $fp   = $solicitudData['fp'] ?? null;
                $rf   = $solicitudData['rf'] ?? null;
                $datosFacturacionId = $solicitudData['datos_facturacion_id'] ?? null;
                $razonSocialId = $solicitudData['razon_social_id'] ?? null;

                if ($solicitudPago && ! $solicitudPago->tiene_factura) {
                    $solicitudPago->update([
                        'uso' => $uso,
                        'mp'  => $mp,
                        'fp'  => $fp,
                        'rf'  => $rf,
                        'datos_facturacion_id' => $datosFacturacionId,
                        'razon_social_id' => $razonSocialId,
                    ]);

                    if ($proveedorUsuarioPrincipal) {
                        $notificaciones[] = [
                            'tipo' => 'factura_pendiente',
                            'data' => [
                                $solicitudPago->numero_folio_solicitud,
                                $solicitudPago->id,
                                $solicitudPago->proveedor_id,
                                $validated['monto_total'],
                                $proveedorUsuarioPrincipal->id
                            ]
                        ];
                    }
                }
            }

            DB::commit();

            DB::afterCommit(function () use ($notificaciones, $proveedorUsuarioPrincipal) {

                foreach ($notificaciones as $n) {

                    switch ($n['tipo']) {

                        case 'pagada':
                            $proveedorUsuarioPrincipal?->notify(new SolicitudPagoPagadaNotification(...$n['data']));
                            // Log::info('✅ Notificación enviada a InterAPI: Pagada', [ 'data' => $n['data'], ]);
                            break;

                        case 'abonada':
                            $proveedorUsuarioPrincipal?->notify(new SolicitudPagoAbonadaNotification(...$n['data']));
                            // Log::info('✅ Notificación enviada a InterAPI: Abonada', [ 'data' => $n['data'], ]);
                            break;

                        case 'factura_pendiente':
                            $proveedorUsuarioPrincipal?->notify(new SolicitudPagoFacturaPendienteNotification(...$n['data']));
                            // Log::info('✅ Notificación enviada a InterAPI: Factura pendiente', [ 'data' => $n['data'], ]);
                            break;

                        case 'pagada_user_construcc':
                            $response = $this->interApiService->spPagoNotifyUsuarioConstrucc(...$n['data']);
                            // Log::info('✅ Notificación enviada a InterAPI: Registro PAgo Prov2', [ 'data' => $n['data'], 'response' => $response ]);
                            break;
                    }
                }
            });

            $pago->load([
                'empresaConstrucc',
                'proveedor',
                'solicitudesPago' => function ($query) {
                    $query->withPivot([
                        'solicitud_pago_id',
                        'monto_aplicado',
                        'estado_pago',
                        'notas',
                        'fecha_aplicacion'
                    ]);
                }
            ]);

            return $this->success(
                new ConstruccPagoResource($pago),
                'Pago registrado y aplicado exitosamente.',
                201
            );
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return $this->handleRegistrarPagoError(
                $e,
                'Error inesperado al registrar el pago del proveedor.',
                [
                    'proveedor_id' => $proveedor->id,
                ]
            );
        }
    }




    /**
     * Descargar comprobante de pago
     * 
     * POST /api/construcc/pagos-spp/pagos/{pago}/descargar-comprobante
     */
    public function descargarComprobantePago(Request $request, PagoSPP $pago)
    {
        // Verificar que el pago tiene comprobante
        if (! $pago->comprobante_pago || ! Storage::disk('private')->exists($pago->comprobante_pago)) {
            return $this->error('Comprobante de pago no disponible.', null, 404);
        }
        return response()->download(
            Storage::disk('private')->path($pago->comprobante_pago)
        );
    }

    /**
     * 
     */
    public function cuentasPorProveedor(Proveedor $proveedor): JsonResponse
    {
        $cuentas = $proveedor->cuentasBancarias()->get();

        return $this->success([
            'proveedor' => [
                'id' => $proveedor->id,
                'nombre_comercial' => $proveedor->nombre_comercial,
            ],
            'cuentas_bancarias' => $cuentas,
        ], 'Cuentas bancarias del proveedor obtenidas exitosamente.');
    }

    private function handleRegistrarPagoError(\Throwable $e, string $message, array $context = []): JsonResponse
    {
        $errorRef = (string) Str::uuid();

        Log::error($message, array_merge($context, [
            'error_ref' => $errorRef,
            'exception_class' => get_class($e),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]));

        return $this->error(
            'No se pudo registrar el pago. Si el problema continua, comparte este codigo con soporte.',
            ['error_ref' => $errorRef],
            500
        );
    }
}
