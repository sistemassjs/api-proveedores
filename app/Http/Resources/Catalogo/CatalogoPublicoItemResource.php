<?php

namespace App\Http\Resources\Catalogo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogoPublicoItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'marca' => $this->marca,
            'categoria' => $this->categoria,
            'subcategoria' => $this->subcategoria,
            'unidad' => $this->unidad,
            'modelo' => $this->modelo,
            'empresa' => $this->empresa,
            'logo' => $this->logo,
            'imagen' => $this->imagen,
            'precio_base' => $this->precio_base !== null ? (float) $this->precio_base : null,
            'precio_mayoreo' => $this->precio_mayoreo !== null ? (float) $this->precio_mayoreo : null,
            'precio_menudeo' => $this->precio_menudeo !== null ? (float) $this->precio_menudeo : null,
            'propiedades' => $this->propiedades,
            'activo' => (bool) $this->activo,
            'mostrar_en_listado' => (bool) ($this->mostrar_en_listado ?? true),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
