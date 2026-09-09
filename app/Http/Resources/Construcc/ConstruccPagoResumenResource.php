<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccPagoResumenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio_pago_spp_consecutivo' => $this->folio_pago_spp_consecutivo,
            'fecha_pago' => optional($this->fecha_pago)?->toDateTimeString(),
            'fecha_registro' => optional($this->fecha_registro)?->toDateTimeString(),
        ];
    }
}
