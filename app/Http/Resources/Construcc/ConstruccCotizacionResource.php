<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccCotizacionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'fecha_cotizacion' => $this->fecha_cotizacion,
            'fecha_vencimiento' => $this->fecha_vencimiento,
            'total' => $this->total,
            'observaciones' => $this->observaciones,
            'detalles' => ConstruccCotizacionDetalleResource::collection($this->whenLoaded('detalles')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
