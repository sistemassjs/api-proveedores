<?php

namespace App\Http\Resources;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ProveedorPublicResource extends JsonResource
{
    /**
     * Convierte un string a mayúsculas (UTF-8). Null o vacío se devuelven tal cual.
     */
    private static function upper(?string $value): ?string
    {
        return $value !== null && $value !== '' ? Str::upper($value) : $value;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logo' => PublicStorageUrl::make($this->logo),
            'nombre_comercial' => self::upper($this->nombre_comercial),
            'email' => $this->email,
            'telefono' => $this->telefono,
            'direccion_empresa' => self::upper($this->direccion_empresa),
            'constancia_fiscal' => PublicStorageUrl::make($this->constancia_fiscal),
        ];
    }
}
