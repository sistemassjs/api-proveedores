<?php

namespace App\Http\Resources;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarcaResource extends JsonResource
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
            'logo' => PublicStorageUrl::make($this->logo),
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'productos_count' => $this->productos_count, // 👈 aquí lo expones
            'estatus' => $this->estatus,
            // 'created_at' => $this->created_at?->toDateTimeString(),
            // 'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
