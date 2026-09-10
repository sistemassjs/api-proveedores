<?php

namespace App\Http\Resources\Producto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoEspecificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'atributo' => $this->atributo,
            'clave' => $this->atributo,
            'valor' => $this->valor,
            'unidad' => $this->unidad,
            'orden' => (int) $this->orden,
        ];
    }
}
