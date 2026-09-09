<?php

namespace App\Http\Resources\Construcc;

use App\Enums\EstadoSP;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccSolicitudPagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $saldoPendiente = $this->calcularSaldoRestante();
        $montoAbonado   = $this->calcularMontoAbonado();

        // Última fecha de pago
        $fechaUltimoPago = null;
        if ($this->estado_solicitud === EstadoSP::PAGADO->value && $this->relationLoaded('pagos')) {
            $ultimoPago = $this->pagos
                ->sortByDesc(fn($pago) => $pago->pivot->fecha_aplicacion)
                ->first();
            $fechaUltimoPago = $ultimoPago?->pivot->fecha_aplicacion?->toDateTimeString();
        }
        return [
            'id' => $this->id,
            'numero_folio_solicitud' => $this->numero_folio_solicitud,
            'folio_sp_consecutivo' => $this->folio_sp_consecutivo,
            'folio_factura' => $this->folio_factura,
            'tiene_factura' => $this->tiene_factura,
            'usuario' => $this->usuario_id, //???   
            // 'datos_factura_xml' => $this->datos_factura_xml,
            'descripcion_concepto' => $this->descripcion_concepto,
            'observaciones' => $this->observaciones,
            'notas' => $this->notas,
            'utilizara' => $this->utilizara,
            'equipo' => $this->equipo,
            'equipo_id' => $this->equipo_id,
            'estado_solicitud' => $this->estado_solicitud,
            'proveedor' => new ConstruccProveedorResource($this->whenLoaded('proveedor')),
            'cuentas_bancarias' => ConstruccCuentaBancariaResource::collection(
                $this->whenLoaded('cuentasBancarias')
            ),
            'cotizacion' => new ConstruccCotizacionResource($this->whenLoaded('cotizacion')),
            'ruta_archivo_factura_xml' => $this->ruta_archivo_factura_xml,
            'ruta_archivo_factura_pdf' => $this->ruta_archivo_factura_pdf,
            'ruta_archivo_cotizacion' => $this->ruta_archivo_cotizacion,
            'ruta_archivo_comprobante_pago' => $this->ruta_archivo_comprobante_pago,

            // NUEVO CAMPO
            'verificada' => $this->verificada ? 1 : 0,

            // Usuario Construcc que generó la SP
            'usuario_id' => $this->usuario_id,
            'usuario_nombre' => $this->usuario_nombre,
            'empresa_construcc_id' => $this->empresa_construcc_id,
            'cuenta_bancaria_empresa_construcc_id' => $this->cuenta_bancaria_empresa_construcc_id,

            // Campos de tipo y origen
            'tipo' => $this->tipo,
            'tipo_id' => $this->tipo_id,
            'obra_id' => $this->obra_id,
            'observaciones' => $this->observaciones,
            'notas' => $this->notas,
            'utilizara' => $this->utilizara,
            'equipo' => $this->equipo,

            // Archivos con URLs correctas
            'url_comprobante_pago' => $this->ruta_archivo_comprobante_pago
                ? route('construcc.solicitudes-pago.descargar-comprobante', $this->id)
                : null,

            'url_factura_pdf' => $this->ruta_archivo_factura_pdf
                ? route('construcc.solicitudes-pago.descargar-factura-pdf', $this->id)
                : null,

            'url_factura_xml' => $this->ruta_archivo_factura_xml
                ? route('construcc.solicitudes-pago.descargar-factura-xml', $this->id)
                : null,

            'url_cotizacion' => $this->ruta_archivo_cotizacion
                ? route('construcc.solicitudes-pago.descargar-cotizacion', $this->id)
                : null,

            // Campos de aprobación
            'dg' => $this->dg?->value,
            'dg_fecha' => $this->dg_fecha?->format('Y-m-d H:i:s'),

            'dt' => $this->dt?->value,
            'dt_fecha' => $this->dt_fecha?->format('Y-m-d H:i:s'),

            'pc' => $this->pc?->value,
            'pc_fecha' => $this->pc_fecha?->format('Y-m-d H:i:s'),

            'si' => $this->si?->value,
            'si_fecha' => $this->si_fecha?->format('Y-m-d H:i:s'),

            'da' => $this->da?->value,
            'da_fecha' => $this->da_fecha?->format('Y-m-d H:i:s'),

            'ro' => $this->ro?->value,
            'ro_fecha' => $this->ro_fecha?->format('Y-m-d H:i:s'),

            'estado_solicitud' => $this->estado_solicitud,


            // comentado el: 06/04/2026
            // 'motivo_rechazo' => $this->motivo_rechazo,
            // 'fecha_rechazo' => $this->fecha_rechazo?->format('Y-m-d H:i:s'),
            // 'fecha_pago' => $this->fecha_pago?->format('Y-m-d H:i:s'),

            // 'fecha_comprobante_pago' => $this->fecha_comprobante_pago?->format('Y-m-d H:i:s'),

            // Campos de abono y pagos parciales
            // estos campos deben reflejar lo de pagos 
            'monto_total' => (float) $this->monto_total,
            'monto_abonado' => $montoAbonado,
            'saldo_pendiente' => $saldoPendiente,

            // comentado el: 06/04/2026
            // 'monto_total' => (float) $this->monto_total,
            // 'monto_abonado' => (float) $this->monto_abonado,
            // 'saldo_pendiente' => (float) $this->saldo_pendiente,
            // 'pago_completo' => (bool) $this->pago_completo,
            // 'notas_abono' => $this->notas_abono,
            // 'porcentaje_pagado' => $this->monto_total > 0 ? round(($this->monto_abonado / $this->monto_total) * 100, 2) : 0,

            // ✅ Solo para pagadas
            'fecha_pago' => $fechaUltimoPago,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Campos de autorización parcial
            'monto_autorizado' => (float) $this->monto_autorizado,
            'usuario_autorizo_parcial_id' => $this->usuario_autorizo_parcial_id,
            'usuario_autorizo_parcial_nombre' => $this->usuario_autorizo_parcial_nombre,
            'motivo_autorizacion_parcial' => $this->motivo_autorizacion_parcial,
            'fecha_autorizacion_parcial' => $this->fecha_autorizacion_parcial,

            // ?->format('Y-m-d H:i:s'),
            'validacion_con_monto' => [
                'monto' => (float) $this->validacion_monto,
                'usuario_id' => $this->validacion_usuario_id,
                'usuario_nombre' => $this->validacion_usuario_nombre,
                'fecha' => $this->validacion_fecha,
                'motivo' => $this->validacion_motivo,
            ],

            'pagos' => $this->whenLoaded('pagos', function () {
                return $this->pagos
                    ->sortByDesc(fn($pago) => $pago->pivot->fecha_aplicacion)
                    ->values()
                    ->map(function ($pago) {

                        $pivot = $pago->pivot;

                        return [
                            'id' => $pago->id,
                            'folio' => $pago->folio_pago_spp_consecutivo,
                            'monto_total_pago' => (float) $pago->monto_total,

                            'monto_aplicado' => (float) ($pivot->monto_aplicado ?? 0),
                            'estado_pago' => $pivot->estado_pago ?? null,
                            'fecha_aplicacion' => optional($pivot->fecha_aplicacion)?->toDateTimeString(),

                            'autorizacion_pago' => [
                                'usuario_id' => $pivot->usuario_autorizo_id ?? null,
                                'usuario_nombre' => $pivot->usuario_autorizo_nombre ?? null,
                                'monto_autorizado' => isset($pivot->monto_autorizado)
                                    ? (float) $pivot->monto_autorizado
                                    : null,
                                'motivo' => $pivot->motivo_autorizacion ?? null,
                                'fecha' => optional($pivot->fecha_autorizacion)?->toDateTimeString(),
                            ],
                        ];
                    });
            }),
        ];
    }
}
