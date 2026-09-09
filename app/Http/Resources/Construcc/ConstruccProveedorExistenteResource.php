<?php

namespace App\Http\Resources\Construcc;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para búsqueda de proveedor por criterio (RFC, email, razón social, teléfono).
 * Formato: id, razon_social, nombre_comercial, rfc, email.
 */
class ConstruccProveedorExistenteResource extends JsonResource
{
    /**
     * Resuelve el array de datos del proveedor o null.
     *
     * @return array{id: int, razon_social: string, nombre_comercial: string, rfc: string|null, email: string|null}|null
     */
    public static function toBusquedaArray(?Proveedor $proveedor): ?array
    {
        return $proveedor ? (new self($proveedor))->resolve() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'razon_social' => $this->razon_social,
            'nombre_comercial' => $this->nombre_comercial,
            'rfc' => $this->rfc,
            'email' => $this->email,
        ];
    }
}
