<?php

namespace App\Http\Resources\Presupuesto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresupuestoSugerenciaLineaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'origen' => $this['origen'] ?? null,
            'id' => $this['id'] ?? null,
            'nombre' => $this['nombre'] ?? null,
            'unidad' => $this['unidad'] ?? null,
            'precio_unitario' => array_key_exists('precio_unitario', $this->resource)
                && $this['precio_unitario'] !== null
                && $this['precio_unitario'] !== ''
                    ? (float) $this['precio_unitario']
                    : null,
            'empresa' => $this['empresa'] ?? null,
            'logo' => $this['logo'] ?? null,
            'proveedor_id' => $this['proveedor_id'] ?? null,
            'categoria_ui' => $this['categoria_ui'] ?? null,
            'es_compuesto' => (bool) ($this['es_compuesto'] ?? false),
            'clave' => $this['clave'] ?? null,
            'marca' => $this['marca'] ?? null,
            'familia' => $this['familia'] ?? null,
            'subcategoria' => $this['subcategoria'] ?? null,
            'descripcion' => $this['descripcion'] ?? null,
            'codigo' => $this['codigo'] ?? null,
            'imagen_url' => $this['imagen_url'] ?? null,
            'imagen_path' => $this['imagen_path'] ?? null,
            'imagen_base64' => $this['imagen_base64'] ?? null,
        ];
    }
}
