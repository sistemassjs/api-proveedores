<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccCategoriaResource extends JsonResource
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
            'nivel' => $this->nivel,
            'parent_id' => $this->parent_id,
            'activo' => (bool) $this->activo,

            // Información del proveedor (solo ID para optimizar)
            'proveedor_id' => $this->proveedor_id,

            // Relación con categoria padre
            'parent' => $this->when($this->relationLoaded('parent') && $this->parent, function () {
                return [
                    'id' => $this->parent->id,
                    'nombre' => $this->parent->nombre,
                    'descripcion' => $this->parent->descripcion,
                ];
            }),

            // Subcategorías (categorías hijas)
            'subcategorias' => $this->when($this->relationLoaded('children'), function () {
                return $this->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'nombre' => $child->nombre,
                        'descripcion' => $child->descripcion,
                        'nivel' => $child->nivel,
                        'activo' => (bool) $child->activo,
                        'productos_count' => $child->productos_count ?? 0,
                    ];
                });
            }),

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
                'resource_type' => 'categoria',
                'version' => '1.0',
            ],
        ];
    }
}
