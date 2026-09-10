<?php

namespace App\Http\Resources;

use App\Http\Resources\Catalogo\CatalogoFamiliaResource;
use App\Http\Resources\Catalogo\CatalogoSubfamiliaResource;
use App\Http\Resources\Producto\ProductoDocumentoResource;
use App\Http\Resources\Producto\ProductoEspecificacionResource;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProveedorProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'codigo_interno' => $this->codigo_interno,
            'codigo_fabricante' => $this->codigo_fabricante,
            'codigo_barras' => $this->codigo_barras,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'modelo' => $this->modelo,
            'tipo' => $this->tipo,

            'precio_base' => (float) $this->precio_base,
            'precio_mayoreo' => (float) $this->precio_mayoreo,
            'precio_menudeo' => (float) $this->precio_menudeo,

            'presentacion' => $this->presentacion,
            'cantidad_contenida' => $this->cantidad_contenida,
            'factor_conversion' => $this->factor_conversion,
            'disponibilidad' => $this->disponibilidad,
            'tiempo_entrega' => $this->tiempo_entrega,
            'url_producto' => $this->url_producto,
            'tags' => $this->tags,

            'imagen_principal' => PublicStorageUrl::make($this->imagen_principal),
            'marca_id' => $this->marca_id,
            'categoria_id' => $this->categoria_id,
            'subcategoria_id' => $this->subcategoria_id,
            'familia_id' => $this->familia_id,
            'subfamilia_id' => $this->subfamilia_id,
            'proveedor_id' => $this->proveedor_id,
            'unidad_medida_id' => $this->unidad_medida_id,
            'unidad_contenido_id' => $this->unidad_contenido_id,
            'unidad_base_id' => $this->unidad_base_id,

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

            'familia' => $this->when(
                $this->relationLoaded('familia'),
                fn () => $this->familia ? new CatalogoFamiliaResource($this->familia) : null
            ),

            'subfamilia' => $this->when(
                $this->relationLoaded('subfamilia'),
                fn () => $this->subfamilia ? new CatalogoSubfamiliaResource($this->subfamilia) : null
            ),

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

            'unidad_contenido' => $this->when($this->relationLoaded('unidadContenido'), function () {
                return $this->unidadContenido ? [
                    'id' => $this->unidadContenido->id,
                    'nombre' => $this->unidadContenido->nombre,
                    'clave' => $this->unidadContenido->clave,
                ] : null;
            }),

            'unidad_base' => $this->when($this->relationLoaded('unidadBase'), function () {
                return $this->unidadBase ? [
                    'id' => $this->unidadBase->id,
                    'nombre' => $this->unidadBase->nombre,
                    'clave' => $this->unidadBase->clave,
                ] : null;
            }),

            'especificaciones' => $this->whenLoaded(
                'especificaciones',
                fn () => ProductoEspecificacionResource::collection($this->especificaciones)
            ),
            'documentos' => $this->whenLoaded(
                'documentos',
                fn () => ProductoDocumentoResource::collection($this->documentos)
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'estatus' => $this->estatus,
            'activo' => (bool) $this->activo,
        ];
    }
}
