<?php

namespace App\Http\Resources\Catalogo;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape compatible con PresupuestoSugerenciaLineaResource (origen catalogo).
 */
class CatalogoEmpresaProductoSugerenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proveedor = $this->relationLoaded('proveedor') ? $this->proveedor : null;
        $empresa = $proveedor
            ? trim((string) ($proveedor->razon_social ?: $proveedor->nombre_comercial))
            : null;

        $unidad = null;
        if ($this->relationLoaded('unidad_medida') && $this->unidad_medida) {
            $unidad = $this->unidad_medida->clave ?: $this->unidad_medida->nombre;
        }

        $precio = $this->precio_base;

        return [
            'origen' => 'catalogo',
            'id' => $this->id,
            'nombre' => $this->nombre,
            'unidad' => $unidad,
            'precio_unitario' => $precio !== null && $precio !== ''
                ? (float) $precio
                : null,
            'empresa' => $empresa !== '' ? $empresa : null,
            'logo' => $proveedor ? PublicStorageUrl::make($proveedor->logo) : null,
            'proveedor_id' => (int) $this->proveedor_id,
            'categoria_ui' => $this->tipo ?: 'producto',
            'marca' => $this->relationLoaded('marca') && $this->marca
                ? $this->marca->nombre
                : null,
            'familia' => $this->relationLoaded('familia') && $this->familia
                ? $this->familia->nombre
                : null,
            'subcategoria' => $this->relationLoaded('subfamilia') && $this->subfamilia
                ? $this->subfamilia->nombre
                : null,
            'descripcion' => $this->descripcion,
            'codigo' => $this->codigo_interno ?: $this->sku,
            'imagen_url' => PublicStorageUrl::make($this->imagen_principal),
            'imagen_path' => null,
            'imagen_base64' => null,
        ];
    }
}
