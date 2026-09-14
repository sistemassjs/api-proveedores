<?php

namespace App\Http\Resources\Catalogo;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle compatible con CatalogoPublicoItemDetalleResource del front.
 */
class CatalogoEmpresaProductoDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proveedor = $this->relationLoaded('proveedor') ? $this->proveedor : null;
        $empresa = $proveedor
            ? trim((string) ($proveedor->razon_social ?: $proveedor->nombre_comercial))
            : '';

        $unidad = null;
        if ($this->relationLoaded('unidad_medida') && $this->unidad_medida) {
            $unidad = $this->unidad_medida->clave ?: $this->unidad_medida->nombre;
        }

        return [
            'id' => $this->id,
            'codigo' => $this->codigo_interno ?: $this->sku,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'marca' => $this->relationLoaded('marca') && $this->marca
                ? $this->marca->nombre
                : null,
            'categoria' => $this->relationLoaded('familia') && $this->familia
                ? $this->familia->nombre
                : null,
            'subcategoria' => $this->relationLoaded('subfamilia') && $this->subfamilia
                ? $this->subfamilia->nombre
                : null,
            'unidad' => $unidad,
            'modelo' => $this->modelo,
            'empresa' => $empresa !== '' ? $empresa : ('Proveedor #'.$this->proveedor_id),
            'logo' => $proveedor ? PublicStorageUrl::make($proveedor->logo) : null,
            'proveedor_id' => (int) $this->proveedor_id,
            'imagen' => PublicStorageUrl::make($this->imagen_principal),
            'precio_base' => $this->precio_base !== null ? (float) $this->precio_base : null,
            'precio_mayoreo' => $this->precio_mayoreo !== null ? (float) $this->precio_mayoreo : null,
            'precio_menudeo' => $this->precio_menudeo !== null ? (float) $this->precio_menudeo : null,
            'mostrar_en_catalogo_publico' => (bool) $this->mostrar_en_catalogo_publico,
        ];
    }
}
