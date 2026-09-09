<?php

namespace App\Http\Resources\Tienda;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TiendaMarcaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'nombre' => $this->nombre,
            'logo' => PublicStorageUrl::make($this->logo),
            'activa' => (bool) $this->activa,
            'color' => $this->color,
            'totalProductos' => (int) $this->total_productos,
        ];
    }
}
