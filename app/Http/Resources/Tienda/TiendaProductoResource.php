<?php

namespace App\Http\Resources\Tienda;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class TiendaProductoResource extends JsonResource
{
    public function toArray($request)
    {
        $imagenes = [];
        if ($this->relationLoaded('imagenes') && $this->imagenes) {
            $imagenes = $this->imagenes
                ->map(fn ($img) => PublicStorageUrl::make($img->url_imagen))
                ->filter()
                ->values()
                ->all();
        }

        $especificaciones = [];
        if ($this->relationLoaded('especificaciones') && $this->especificaciones) {
            $especificaciones = $this->especificaciones
                ->mapWithKeys(fn ($esp) => [($esp->atributo ?? $esp->id) => $esp->valor ?? null])
                ->all();
        }

        return [
            'id' => (string) $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'precio' => (float) ($this->precio_base ?? $this->precio ?? 0),
            'precioAnterior' => $this->when(
                isset($this->precio_mayoreo) && (float) $this->precio_mayoreo > 0,
                (float) $this->precio_mayoreo
            ),
            'imagen_principal' => PublicStorageUrl::make($this->imagen_principal) ?? '',
            'imagenes' => $imagenes,
            'marca' => new TiendaMarcaResource($this->whenLoaded('marca')),
            'proveedor' => new TiendaProveedorResource($this->whenLoaded('proveedor')),
            'categoria' => new TiendaCategoriaResource($this->whenLoaded('categoria')),
            'stock' => (int) ($this->stock ?? 0),
            'especificaciones' => $especificaciones,
            'puntuacion' => (float) ($this->puntuacion ?? 0),
            'totalPedidos' => (int) ($this->total_pedidos ?? 0),
            'fechaCreacion' => $this->created_at ? $this->created_at->toISOString() : null,
            'activo' => (bool) $this->activo,
            'destacado' => (bool) $this->destacado,
            'enOferta' => (bool) ($this->en_oferta ?? false),
            'tags' => $this->tags ?? [],
        ];
    }
}
