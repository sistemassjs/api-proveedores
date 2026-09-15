<?php

namespace App\Http\Resources\SolicitudPago;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudPagoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Comprobante: camino SPP (legado) o pago asociado (pagos-spp); URL según origen
        $rutaComprobante = $this->resolverRutaComprobantePago();
        $urlComprobante = $this->resolverUrlComprobantePago();

        return [
            'id' => $this->id,
            'numero_folio_solicitud' => $this->numero_folio_solicitud,
            'folio_sp_consecutivo' => $this->folio_sp_consecutivo,
            'folio_factura' => $this->folio_factura,
            'tiene_factura' => $this->tiene_factura,
            // 'datos_factura_xml' => $this->datos_factura_xml,
            'descripcion_concepto' => $this->descripcion_concepto,
            'observaciones' => $this->observaciones,
            'notas' => $this->notas,
            'utilizara' => $this->utilizara,
            'equipo' => $this->equipo,
            'equipo_id' => $this->equipo_id,
            'estado_solicitud' => $this->estado_solicitud,
            'item_visto' => (bool) ($this->item_visto ?? false),
            'cuentas_bancarias' => SolicitudPagoCuentaBancariaResource::collection($this->whenLoaded('cuentasBancarias')),

            'proveedor' => $this->whenLoaded('proveedor', function () {
                return [
                    'id' => $this->proveedor->id,
                    'nombre' => $this->proveedor->nombre,
                    'razon_social' => $this->proveedor->razon_social,
                    'rfc' => $this->proveedor->rfc,
                    'representante_legal' => $this->proveedor->representante_legal,
                ];
            }),

            'empresa_construcc' => $this->whenLoaded('empresaConstrucc', function () {
                return [
                    'id' => $this->empresaConstrucc->id,
                    'nombre' => $this->empresaConstrucc->nombre,
                    'razon_social' => $this->empresaConstrucc->razon_social,
                    'rfc' => $this->empresaConstrucc->rfc,
                    'representante_legal' => $this->empresaConstrucc->representante_legal,
                ];
            }),

            // Usuario Construcc que generó la SP
            'usuario_id' => $this->usuario_id,
            'usuario_nombre' => $this->usuario_nombre,
            'sucursal_id' => $this->sucursal_id,
            'cotizacion_id' => $this->cotizacion_id,

            // Archivos (comprobante efectivo para chips; URL apunta al origen real)
            'ruta_archivo_factura_xml' => $this->ruta_archivo_factura_xml,
            'ruta_archivo_factura_pdf' => $this->ruta_archivo_factura_pdf,
            'ruta_archivo_cotizacion' => $this->ruta_archivo_cotizacion,
            'ruta_archivo_comprobante_pago' => $rutaComprobante,

            // Archivos con URLs correctas
            'url_comprobante_pago' => $urlComprobante,
            'extension_comprobante_pago' => $rutaComprobante
                ? $this->extensionComprobantePago()
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

            // Fechas principales
            'fecha_registro_pendiente' => $this->fecha_registro_pendiente?->format('Y-m-d H:i:s'),
            'fecha_inicio_procesamiento' => $this->fecha_inicio_procesamiento?->format('Y-m-d H:i:s'),
            'fecha_aprobado' => $this->fecha_aprobado?->format('Y-m-d H:i:s'),
            'fecha_rechazado' => $this->fecha_rechazado?->format('Y-m-d H:i:s'),
            'fecha_con_comprobante' => $this->fecha_con_comprobante?->format('Y-m-d H:i:s'),
            'fecha_confirmacion_pago' => $this->fecha_confirmacion_pago?->format('Y-m-d H:i:s'),

            // Nuevos campos booleanos + fechas
            'dg' => (bool) $this->dg,
            'dg_fecha' => $this->dg_fecha?->format('Y-m-d H:i:s'),

            'dt' => (bool) $this->dt,
            'dt_fecha' => $this->dt_fecha?->format('Y-m-d H:i:s'),

            'pc' => (bool) $this->pc,
            'pc_fecha' => $this->pc_fecha?->format('Y-m-d H:i:s'),

            'si' => (bool) $this->si,
            'si_fecha' => $this->si_fecha?->format('Y-m-d H:i:s'),

            'da' => (bool) $this->da,
            'da_fecha' => $this->da_fecha?->format('Y-m-d H:i:s'),

            'ro' => (bool) $this->ro,
            'ro_fecha' => $this->ro_fecha?->format('Y-m-d H:i:s'),

            'estado_solicitud' => $this->estado_solicitud,
            'motivo_rechazo' => $this->motivo_rechazo,
            'fecha_rechazo' => $this->fecha_rechazo?->format('Y-m-d H:i:s'),
            'fecha_pago' => $this->fecha_pago?->format('Y-m-d H:i:s'),

            // Campos de abono y pagos parciales
            'monto_total' => (float) $this->monto_total,
            'monto_abonado' => (float) $this->monto_total - $this->calcularSaldoRestante(),
            // 'saldo_pendiente' => (float) $this->saldo_pendiente,
            'saldo_pendiente' => (float) $this->calcularSaldoRestante(),
            'pago_completo' => (bool) $this->pago_completo,
            'notas_abono' => $this->notas_abono,
            'porcentaje_pagado' => $this->monto_total > 0 ? round((($this->monto_total - $this->calcularSaldoRestante()) / $this->monto_total) * 100, 2) : 0,

            // Información de Órdenes de Compra
            'origen_oc' => (bool) $this->origen_oc,
            'referencia_oc' => $this->referencia_oc,
            'monto_oc_original' => (float) $this->monto_oc_original,
            'es_de_orden_compra' => $this->esDeOrdenCompra(),

            // Órdenes de compra asociadas
            'ordenes_compra' => $this->whenLoaded('ordenesCompra', function () {
                return $this->ordenesCompra->map(function ($oc) {
                    return [
                        'id' => $oc->id,
                        'numero_orden' => $oc->numero_orden,
                        'fecha_orden' => $oc->fecha_orden?->format('Y-m-d'),
                        'importe_total' => (float) $oc->importe_total,
                        'estado' => [
                            'codigo' => $oc->estado->value,
                            'label' => $oc->estado->label(),
                            'color' => $oc->estado->color(),
                        ],
                        'monto_disponible' => (float) $oc->getMontoDisponible(),

                        // Información de vinculación
                        'vinculacion' => [
                            'monto_asociado' => (float) $oc->pivot->monto_asociado,
                            'fecha_vinculacion' => $oc->pivot->fecha_vinculacion?->format('Y-m-d H:i:s'),
                            'notas' => $oc->pivot->notas,
                        ],
                    ];
                });
            }),

            // Resumen de OC (cuando no se cargan las relaciones completas)
            'oc_resumen' => $this->when($this->origen_oc && ! $this->relationLoaded('ordenesCompra'), function () {
                return [
                    'tiene_oc_asociadas' => true,
                    'referencia_principal' => $this->referencia_oc,
                    'monto_oc_original' => (float) $this->monto_oc_original,
                ];
            }),

            // datos facutracion
            'tiene_factura' => $this->tiene_factura,
            // 'datos_facturacion' => [
            //     'uso' => $this->uso,
            //     'mp' => $this->mp,
            //     'fp' => $this->fp,
            //     'datos_facturacion_id' => $this->datos_facturacion_id,
            // ],
            // Metadatos
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
