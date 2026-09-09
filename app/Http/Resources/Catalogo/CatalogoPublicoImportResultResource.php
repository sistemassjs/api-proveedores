<?php

namespace App\Http\Resources\Catalogo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogoPublicoImportResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => (int) ($this['total'] ?? 0),
            'nuevos' => (int) ($this['nuevos'] ?? 0),
            'actualizados' => (int) ($this['actualizados'] ?? 0),
            'omitidos' => (int) ($this['omitidos'] ?? 0),
            'errores' => $this['errores'] ?? [],
        ];
    }
}
