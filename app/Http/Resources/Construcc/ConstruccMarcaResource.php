<?php

namespace App\Http\Resources\Construcc;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccMarcaResource extends JsonResource
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
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'activo' => (bool) $this->activo,

            // Logo optimizado
            'logo' => PublicStorageUrl::make($this->logo),

            // Información del proveedor (solo ID para optimizar)
            'proveedor_id' => $this->proveedor_id,

            // Estadísticas de productos
            'productos_count' => $this->when($this->relationLoaded('productos'), function () {
                return $this->productos->count();
            }),

            'productos_activos_count' => $this->when($this->relationLoaded('productos'), function () {
                return $this->productos->where('activo', true)->count();
            }),

            // Metadatos
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource_type' => 'marca',
                'version' => '1.0',
            ],
        ];
    }
}
