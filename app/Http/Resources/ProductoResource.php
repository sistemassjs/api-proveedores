<?php

namespace App\Http\Resources;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_interno' => $this->codigo_interno,
            'sku' => $this->sku,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'imagen_principal' => PublicStorageUrl::make($this->imagen_principal),
            //
            'marca_id' => $this->marca_id,
            'categoria_id' => $this->categoria_id,
            'proveedor_id' => $this->proveedor_id,
            'unidad_medida_id' => $this->unidad_medida_id,

            // Relaciones
            'marca' => new MarcaResource($this->whenLoaded('marca')),
            'categoria' => new CategoriaResource($this->whenLoaded('categoria')),
            'unidad_medida' => new UnidadMedidaResource($this->whenLoaded('unidad_medida')),
            'imagenes' => [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
