<?php

namespace App\Services\Cotizacion;

use App\Enums\EstadoSolicitudCotizacion;
use App\Mail\Cotizacion\CotizacionRespuestaMail;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\SolicitudCotizacion;
use App\Models\SolicitudCotizacionDetalle;
use App\Models\SolicitudCotizacionRespuesta;
use App\Models\User;
use App\Notifications\Cotizacion\SolicitudCotizacionRecibidaNotification;
use App\Support\CotizacionPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SolicitudCotizacionService
{
    public function __construct(
        private readonly WhatsAppCotizacionService $whatsApp
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function crearDesdePublico(Proveedor $proveedor, array $data): SolicitudCotizacion
    {
        return DB::transaction(function () use ($proveedor, $data) {
            $solicitud = SolicitudCotizacion::create([
                'proveedor_id' => $proveedor->id,
                'folio' => SolicitudCotizacion::generarFolio($proveedor->id),
                'origen' => 'publico_cotizador',
                'estatus' => EstadoSolicitudCotizacion::RECIBIDA->value,
                'cliente_nombre' => $data['cliente_nombre'],
                'cliente_email' => $data['cliente_email'],
                'cliente_telefono' => $data['cliente_telefono'] ?? null,
                'cliente_whatsapp' => $data['cliente_whatsapp'] ?? null,
                'cliente_notas' => $data['cliente_notas'] ?? null,
                'total' => 0,
            ]);

            $orden = 0;
            foreach ($data['detalles'] as $linea) {
                $producto = Producto::query()
                    ->where('proveedor_id', $proveedor->id)
                    ->where('id', $linea['producto_id'])
                    ->where('activo', true)
                    ->where('mostrar_en_catalogo_publico', true)
                    ->with('unidad_medida')
                    ->firstOrFail();

                $cantidad = (float) ($linea['cantidad'] ?? 1);
                $precioRef = $producto->precio_base !== null ? (float) $producto->precio_base : null;
                $precio = isset($linea['precio_unitario'])
                    ? (float) $linea['precio_unitario']
                    : ($precioRef ?? 0.0);

                $unidad = null;
                if ($producto->unidad_medida) {
                    $unidad = $producto->unidad_medida->clave ?: $producto->unidad_medida->nombre;
                }

                SolicitudCotizacionDetalle::create([
                    'solicitud_cotizacion_id' => $solicitud->id,
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'descripcion' => $producto->descripcion,
                    'unidad' => $unidad,
                    'codigo' => $producto->codigo_interno ?: $producto->sku,
                    'imagen' => $producto->imagen_principal,
                    'precio_referencia' => $precioRef,
                    'precio_unitario' => $precio,
                    'cantidad' => $cantidad,
                    'es_sugerencia_empresa' => false,
                    'observaciones_linea' => $linea['observaciones_linea'] ?? null,
                    'orden' => $orden++,
                ]);
            }

            $solicitud->recalcularTotal();
            $solicitud->load(['detalles', 'proveedor']);

            $this->notificarEmpresaNexprov($solicitud);

            return $solicitud->fresh(SolicitudCotizacion::eagerLodable());
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizar(SolicitudCotizacion $solicitud, array $data): SolicitudCotizacion
    {
        return DB::transaction(function () use ($solicitud, $data) {
            $cabecera = collect($data)->only([
                'cliente_nombre',
                'cliente_email',
                'cliente_telefono',
                'cliente_whatsapp',
                'cliente_notas',
                'observaciones_internas',
                'estatus',
            ])->filter(fn ($v) => $v !== null)->all();

            if ($cabecera !== []) {
                if (isset($cabecera['estatus']) && in_array($cabecera['estatus'], [
                    EstadoSolicitudCotizacion::CERRADA->value,
                    EstadoSolicitudCotizacion::RECHAZADA->value,
                ], true)) {
                    $cabecera['cerrada_at'] = now();
                }
                $solicitud->update($cabecera);
            }

            if (isset($data['detalles']) && is_array($data['detalles'])) {
                $this->sincronizarDetalles($solicitud, $data['detalles']);
            }

            $solicitud->marcarEnRevisionSiRecibida();
            $solicitud->recalcularTotal();

            return $solicitud->fresh(SolicitudCotizacion::eagerLodable());
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     */
    private function sincronizarDetalles(SolicitudCotizacion $solicitud, array $detalles): void
    {
        $idsMantener = [];
        $orden = 0;

        foreach ($detalles as $linea) {
            if (! empty($linea['eliminar']) && ! empty($linea['id'])) {
                SolicitudCotizacionDetalle::query()
                    ->where('solicitud_cotizacion_id', $solicitud->id)
                    ->where('id', $linea['id'])
                    ->delete();
                continue;
            }

            $payload = $this->resolverPayloadLinea($solicitud, $linea, $orden++);

            if (! empty($linea['id'])) {
                $detalle = SolicitudCotizacionDetalle::query()
                    ->where('solicitud_cotizacion_id', $solicitud->id)
                    ->where('id', $linea['id'])
                    ->first();

                if ($detalle) {
                    $detalle->update($payload);
                    $idsMantener[] = $detalle->id;
                    continue;
                }
            }

            $nuevo = SolicitudCotizacionDetalle::create(array_merge($payload, [
                'solicitud_cotizacion_id' => $solicitud->id,
            ]));
            $idsMantener[] = $nuevo->id;
        }

        SolicitudCotizacionDetalle::query()
            ->where('solicitud_cotizacion_id', $solicitud->id)
            ->when($idsMantener !== [], fn ($q) => $q->whereNotIn('id', $idsMantener))
            ->when($idsMantener === [], fn ($q) => $q) // lista vacía tras solo eliminaciones
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $linea
     * @return array<string, mixed>
     */
    private function resolverPayloadLinea(SolicitudCotizacion $solicitud, array $linea, int $orden): array
    {
        $producto = null;
        if (! empty($linea['producto_id'])) {
            $producto = Producto::query()
                ->where('proveedor_id', $solicitud->proveedor_id)
                ->where('id', $linea['producto_id'])
                ->with('unidad_medida')
                ->first();
        }

        $nombre = $linea['nombre'] ?? $producto?->nombre;
        if (! $nombre) {
            throw new \InvalidArgumentException('Cada línea requiere nombre o producto_id válido.');
        }

        $unidad = $linea['unidad'] ?? null;
        if ($unidad === null && $producto?->unidad_medida) {
            $unidad = $producto->unidad_medida->clave ?: $producto->unidad_medida->nombre;
        }

        $precioRef = array_key_exists('precio_referencia', $linea)
            ? $linea['precio_referencia']
            : ($producto?->precio_base !== null ? (float) $producto->precio_base : null);

        $precio = (float) ($linea['precio_unitario'] ?? $precioRef ?? 0);
        $cantidad = (float) ($linea['cantidad'] ?? 1);

        return [
            'producto_id' => $producto?->id,
            'nombre' => $nombre,
            'descripcion' => $linea['descripcion'] ?? $producto?->descripcion,
            'unidad' => $unidad,
            'codigo' => $linea['codigo'] ?? ($producto?->codigo_interno ?: $producto?->sku),
            'imagen' => $linea['imagen'] ?? $producto?->imagen_principal,
            'precio_referencia' => $precioRef,
            'precio_unitario' => $precio,
            'cantidad' => $cantidad,
            'es_sugerencia_empresa' => (bool) ($linea['es_sugerencia_empresa'] ?? false),
            'motivo_sugerencia' => $linea['motivo_sugerencia'] ?? null,
            'observaciones_linea' => $linea['observaciones_linea'] ?? null,
            'orden' => $linea['orden'] ?? $orden,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function responder(SolicitudCotizacion $solicitud, array $data, ?User $usuario): SolicitudCotizacionRespuesta
    {
        $solicitud->loadMissing(['proveedor', 'detalles']);
        $canales = $data['canales']; // email|whatsapp|ambos
        $mensaje = (string) ($data['mensaje'] ?? '');

        $pdfPath = CotizacionPdf::generarYGuardar($solicitud);

        $emailEstado = null;
        $emailError = null;
        $wa = [
            'modo' => null,
            'estado' => null,
            'link' => null,
            'error' => null,
        ];

        if (in_array($canales, ['email', 'ambos'], true)) {
            try {
                Mail::to($solicitud->cliente_email)->send(
                    new CotizacionRespuestaMail($solicitud, $mensaje, $pdfPath)
                );
                $emailEstado = 'enviado';
            } catch (\Throwable $e) {
                Log::warning('SolicitudCotizacionService: fallo email no bloqueante', [
                    'solicitud_id' => $solicitud->id,
                    'error' => $e->getMessage(),
                ]);
                $emailEstado = 'error';
                $emailError = $e->getMessage();
            }
        }

        if (in_array($canales, ['whatsapp', 'ambos'], true)) {
            $pdfUrl = null;
            // Link privado no es público; el mensaje WA lleva el folio/total. PDF va por email.
            $wa = $this->whatsApp->enviarOGenerarLink($solicitud, $mensaje, $pdfUrl);
        }

        $respuesta = SolicitudCotizacionRespuesta::create([
            'solicitud_cotizacion_id' => $solicitud->id,
            'enviado_por_user_id' => $usuario?->id,
            'canales' => $canales,
            'mensaje' => $mensaje,
            'pdf_path' => $pdfPath,
            'email_estado' => $emailEstado,
            'email_error' => $emailError,
            'whatsapp_modo' => $wa['modo'],
            'whatsapp_estado' => $wa['estado'],
            'whatsapp_link' => $wa['link'],
            'whatsapp_error' => $wa['error'],
            'payload_resumen' => [
                'folio' => $solicitud->folio,
                'total' => (float) $solicitud->total,
                'lineas' => $solicitud->detalles->count(),
                'cliente_email' => $solicitud->cliente_email,
            ],
        ]);

        $canalUtil = ($emailEstado === 'enviado')
            || in_array($wa['estado'], ['link_generado', 'enviado_api'], true);

        if ($canalUtil) {
            $solicitud->update([
                'estatus' => EstadoSolicitudCotizacion::RESPONDIDA->value,
                'respondida_at' => now(),
            ]);
        } else {
            $solicitud->marcarEnRevisionSiRecibida();
        }

        return $respuesta->fresh(['enviadoPor']);
    }

    private function notificarEmpresaNexprov(SolicitudCotizacion $solicitud): void
    {
        try {
            $usuarios = $solicitud->proveedor
                ? $solicitud->proveedor->usuariosActivos()->get()
                : collect();

            foreach ($usuarios as $usuario) {
                $usuario->notify(
                    (new SolicitudCotizacionRecibidaNotification($solicitud))
                        ->forClientApps('nexprov')
                );
            }
        } catch (\Throwable $e) {
            Log::error('SolicitudCotizacionService: error notificando NexProv', [
                'solicitud_id' => $solicitud->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function pdfExiste(?string $path): bool
    {
        return $path && Storage::disk('private')->exists($path);
    }
}
