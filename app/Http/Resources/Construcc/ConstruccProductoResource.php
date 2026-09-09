<?php

namespace App\Http\Resources\Construcc;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccProductoResource extends JsonResource
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
            'sku' => $this->sku,
            'codigo_interno' => $this->codigo_interno,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'modelo' => $this->modelo,

            // Precios
            'precios' => [
                'base' => (float) $this->precio_base,
                'mayoreo' => (float) $this->precio_mayoreo,
                'menudeo' => (float) $this->precio_menudeo,
            ],

            // Stock e inventory
            'inventario' => [
                'stock' => (int) $this->stock,
                'disponible' => $this->stock > 0,
                'activo' => (bool) $this->activo,
            ],

            // Clasificación
            'clasificacion' => [
                'destacado' => (bool) $this->destacado,
                'principal' => (bool) $this->principal,
                'estatus' => $this->estatus,
            ],

            // Imagen principal optimizada
            'imagen_principal' => PublicStorageUrl::make($this->imagen_principal),

            // Relación con proveedor (información básica)
            'proveedor' => $this->when($this->relationLoaded('proveedor'), function () {
                return [
                    'id' => $this->proveedor->id,
                    'nombre_comercial' => $this->proveedor->nombre_comercial,
                    'razon_social' => $this->proveedor->razon_social,
                    'logo' => PublicStorageUrl::make($this->proveedor->logo),
                ];
            }),

            // Categorización del producto
            'categoria' => $this->when($this->relationLoaded('categoria'), function () {
                return $this->categoria ? [
                    'id' => $this->categoria->id,
                    'nombre' => $this->categoria->nombre,
                    'descripcion' => $this->categoria->descripcion,
                ] : null;
            }),

            'subcategoria' => $this->when($this->relationLoaded('subcategoria'), function () {
                return $this->subcategoria ? [
                    'id' => $this->subcategoria->id,
                    'nombre' => $this->subcategoria->nombre,
                    'descripcion' => $this->subcategoria->descripcion,
                ] : null;
            }),

            'marca' => $this->when($this->relationLoaded('marca'), function () {
                return $this->marca ? [
                    'id' => $this->marca->id,
                    'nombre' => $this->marca->nombre,
                    'descripcion' => $this->marca->descripcion,
                    'logo' => PublicStorageUrl::make($this->marca->logo),
                ] : null;
            }),

            'unidad_medida' => $this->when($this->relationLoaded('unidad_medida'), function () {
                return $this->unidad_medida ? [
                    'id' => $this->unidad_medida->id,
                    'nombre' => $this->unidad_medida->nombre,
                    'clave' => $this->unidad_medida->clave,
                    'descripcion' => $this->unidad_medida->descripcion,
                ] : null;
            }),

            // Especificaciones (resumen)
            'especificaciones' => $this->when($this->relationLoaded('especificaciones'), function () {
                return $this->especificaciones->map(function ($spec) {
                    return [
                        'id' => $spec->id,
                        'nombre' => $spec->nombre,
                        'valor' => $spec->valor,
                        'unidad' => $spec->unidad,
                    ];
                });
            }),

            // Imágenes adicionales (solo URLs optimizadas)
            'imagenes' => $this->when($this->relationLoaded('imagenes'), function () {
                return $this->imagenes->map(function ($imagen) {
                    return [
                        'id' => $imagen->id,
                        'url' => PublicStorageUrl::make($imagen->url),
                        'descripcion' => $imagen->descripcion,
                        'orden' => $imagen->orden,
                        'principal' => (bool) $imagen->principal,
                    ];
                });
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
                'resource_type' => 'producto',
                'version' => '1.0',
            ],
        ];
    }
}
