<?php

namespace App\Http\Resources\Cotizacion;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CotizadorProductoSugerenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unidad = null;
        if ($this->relationLoaded('unidad_medida') && $this->unidad_medida) {
            $unidad = $this->unidad_medida->clave ?: $this->unidad_medida->nombre;
        }

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo_interno ?: $this->sku,
            'descripcion' => $this->descripcion,
            'unidad' => $unidad,
            'precio_base' => $this->precio_base !== null ? (float) $this->precio_base : null,
            'stock' => $this->stock !== null ? (float) $this->stock : null,
            'imagen_url' => PublicStorageUrl::make($this->imagen_principal),
            'proveedor_id' => (int) $this->proveedor_id,
        ];
    }
}
