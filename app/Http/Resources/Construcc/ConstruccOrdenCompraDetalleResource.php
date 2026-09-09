<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccOrdenCompraDetalleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto' => $this->producto,
            'descripcion' => $this->descripcion,
            'cantidad' => (float) $this->cantidad,
            'unidad_medida' => $this->unidad_medida,
            'precio_unitario' => (float) $this->precio_unitario,
            'importe' => (float) $this->importe,
            'importe_calculado' => (float) $this->calcularImporte(),

            // Relación con orden de compra (solo ID para evitar ciclos)
            'orden_compra_id' => $this->orden_compra_id,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
