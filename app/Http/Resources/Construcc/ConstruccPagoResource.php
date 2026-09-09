<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle del pago con la solicitud de pago. 
 * Para preacargar los datos: proveedor, SPP se debe usar ->load()
 */
class ConstruccPagoResource extends JsonResource
{
  /**
   * Transform the resource into an array.
   */
  public function toArray(Request $request): array
  {
    // Proveedor seguro (puede venir null)
    $proveedorId = ($this->relationLoaded('proveedor') && $this->proveedor)
      ? $this->proveedor->id
      : null;

    return [
      'id' => $this->id,

      // Datos de empresa construcc
      'folio_pago_spp_consecutivo' => $this->folio_pago_spp_consecutivo,
      'empresa_construcc_id' => $this->empresa_construcc_id,
      'empresa_construcc_nombre' => $this->whenLoaded('empresaConstrucc', fn() => $this->empresaConstrucc?->nombre),
      'cuenta_construcc_id' => $this->cuenta_bancaria_empresa_construcc_id,

      // Usuario
      'usuario_id' => $this->usuario_registro_id,
      'usuario_nombre' => $this->usuario_registro_nombre,

      // Proveedor
      'proveedor_id' => $proveedorId,
      'proveedor_nombre_comercial' => $this->whenLoaded('proveedor', fn() => $this->proveedor?->nombre_comercial),
      'proveedor_razon_social' => $this->whenLoaded('proveedor', fn() => $this->proveedor?->razon_social),
      'proveedor_rfc' => $this->whenLoaded('proveedor', fn() => $this->proveedor?->rfc),

      // Datos del comprobante de pago
      'datos_comprobante' => [
        'comprobante_url' => $this->when(
          $this->comprobante_pago,
          fn() => route('construcc.pagos-spp.proveedor.spp.descargar-comprobante', [
            'pago' => $this->id,
          ])
        ),
        'monto_total' => (float) $this->monto_total,
        'fecha_registro' => optional($this->fecha_registro)?->toDateTimeString(),
        'fecha_pago' => optional($this->fecha_pago)?->toDateTimeString(),
        'referencia_pago' => $this->referencia_pago,
        'banco_destino' => $this->banco_destino,
        'titular_cuenta_destino' => $this->titular_cuenta_destino,
        'clave_rastreo' => $this->clave_rastreo,
      ],

      // Solicitudes de pago aplicadas
      'solicitudes_pago' => $this->whenLoaded('solicitudesPago', function () {
        return $this->solicitudesPago->map(function ($sp) {

          // Si ya vienes con withSum('pagos as total_pagado') se aprovecha
          $saldoPendiente = (float) ($sp->calcularSaldoRestante() ?? 0);
          $montoPagado = (float) ($sp->monto_total - $saldoPendiente);

          return [
            'id' => $sp->id,
            'folio_sp_consecutivo' => $sp->folio_sp_consecutivo ?? null,
            'numero_folio_solicitud' => $sp->numero_folio_solicitud ?? null,

            // Montos
            'monto_total_sp' => (float) $sp->monto_total,
            'monto_pagado' => $montoPagado,
            'saldo_pendiente' => $saldoPendiente,
            'monto_pendiente' => $saldoPendiente,
            'monto_autorizado' => (float) $sp->monto_autorizado ?? null,

            // Bandreas de factura
            'tiene_factura' => $sp->tiene_factura,

            'datos_factura' => [
              'uso' => $sp->uso,
              'mp' => $sp->mp,
              'fp' => $sp->fp,
              'rf' => $sp->rf,
              'razon_social_id' => $sp->razon_social_id,
              'datos_facturacion_id' => $sp->datos_facturacion_id,
            ],

            // // Campos de autorización parcial (si existen)
            // 'usuario_autorizo_parcial_id'     => $this->usuario_autorizo_parcial_id ?? null,
            // 'usuario_autorizo_parcial_nombre' => $this->usuario_autorizo_parcial_nombre ?? null,
            // 'motivo_autorizacion_parcial'     => $this->motivo_autorizacion_parcial ?? null,
            // 'fecha_autorizacion_parcial'      => optional($this->fecha_autorizacion_parcial)?->format('Y-m-d H:i:s'),


            'autorizacion_pago' => [
              'usuario_id' => $sp->pivot->usuario_autorizo_id ?? null,
              'usuario_nombre' => $sp->pivot->usuario_autorizo_nombre ?? null,
              'monto_autorizado' => isset($sp->pivot->monto_autorizado)
                ? (float) $sp->pivot->monto_autorizado
                : null,
              'motivo' => $sp->pivot->motivo_autorizacion ?? null,
              'fecha' => optional($sp->pivot->fecha_autorizacion)?->toDateTimeString(),
            ],

            // Pivot blindado
            'monto_aplicado' => (float) ($sp->pivot->monto_aplicado ?? 0),
            'estado_pago' => $sp->pivot->estado_pago ?? null,
            'notas' => $sp->pivot->notas ?? null,
            'fecha_aplicacion' => optional($sp->pivot->fecha_aplicacion ?? null)?->toDateTimeString(),


          ];
        });
      }),

      // Fechas
      'fecha_pago' => optional($this->fecha_pago)?->toDateTimeString(),
      'fecha_registro' => optional($this->fecha_registro)?->toDateTimeString(),
      'created_at' => optional($this->created_at)?->toDateTimeString(),
      'updated_at' => optional($this->updated_at)?->toDateTimeString(),
    ];
  }
}
