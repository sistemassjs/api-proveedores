<?php

namespace App\Http\Resources\Tienda;

use Illuminate\Http\Resources\Json\JsonResource;

class TiendaAccesoRapidoResource extends JsonResource
{
    public function toArray($request)
    {
        $url = (string) ($this->url ?? '');
        $tipo = $this->tipo ?? $this->inferTipoFromUrl($url);

        return [
            'id' => (string) $this->id,
            'nombre' => $this->nombre ?? $this->titulo,
            'icono' => $this->icono,
            'color' => $this->color,
            'totalProductos' => (int) ($this->total_productos ?? 0),
            'tipo' => $tipo,
            'url' => $url,
            'activo' => (bool) $this->activo,
        ];
    }

    private function inferTipoFromUrl(string $url): string
    {
        if (str_contains($url, 'proveedor')) {
            return 'proveedor';
        }
        if (str_contains($url, 'marca')) {
            return 'marca';
        }
        if (str_contains($url, 'categoria') || str_contains($url, 'catalogo')) {
            return 'categoria';
        }

        return 'categoria';
    }
}
