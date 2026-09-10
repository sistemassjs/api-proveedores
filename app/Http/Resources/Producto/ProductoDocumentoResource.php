<?php

namespace App\Http\Resources\Producto;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoDocumentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'tipo' => $this->tipo,
            'nombre' => $this->nombre,
            'url' => PublicStorageUrl::make($this->url) ?? $this->url,
            'mime' => $this->mime,
            'orden' => (int) $this->orden,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
