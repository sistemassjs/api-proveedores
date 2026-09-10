<?php

namespace App\Http\Resources\Catalogo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogoSubfamiliaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'familia_id' => $this->familia_id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'activo' => (bool) $this->activo,
            'familia' => $this->when(
                $this->relationLoaded('familia') && $this->familia,
                fn () => [
                    'id' => $this->familia->id,
                    'nombre' => $this->familia->nombre,
                    'codigo' => $this->familia->codigo,
                ]
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
