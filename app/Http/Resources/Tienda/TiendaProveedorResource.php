<?php

namespace App\Http\Resources\Tienda;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class TiendaProveedorResource extends JsonResource
{
    private static function upper(?string $value): ?string
    {
        return $value !== null && $value !== '' ? Str::upper($value) : $value;
    }

    public function toArray(Request $request): array
    {
        $nombre = $this->nombre_comercial ?? $this->nombre ?? null;

        return [
            'id' => (string) $this->id,
            'nombre' => self::upper($nombre),
            'logo' => PublicStorageUrl::make($this->logo),
            'principal' => (bool) ($this->principal ?? false),
            'activo' => (bool) ($this->activo ?? ($this->estatus === 'activo')),
            'calificacion' => (float) ($this->calificacion ?? 0),
            'totalProductos' => (int) ($this->total_productos ?? $this->productos_count ?? 0),
            'tiempoEntrega' => $this->tiempo_entrega ?? null,
        ];
    }
}
