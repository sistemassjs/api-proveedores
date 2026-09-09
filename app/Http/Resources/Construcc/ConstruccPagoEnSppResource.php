<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @deprecated Actualmente no esta en uso
 */
class ConstruccPagoEnSppResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,

      'datos_comprobante' => [
        'comprobante_url' => $this->when(
          $this->comprobante_pago,
          fn() => route('construcc.pagos-spp.proveedor.spp.descargar-comprobante', [
            'pago' => $this->id,
          ])
        ),
        'monto_total' => (float) $this->monto_total,
        'fecha_pago' => optional($this->fecha_pago)?->toDateTimeString(),
      ],

      'pivot' => [
        'monto_aplicado' => (float) $this->pivot->monto_aplicado,
        'estado_pago' => $this->pivot->estado_pago,
        'fecha_aplicacion' => optional($this->pivot->fecha_aplicacion)?->toDateTimeString(),
      ],
    ];
  }
}
