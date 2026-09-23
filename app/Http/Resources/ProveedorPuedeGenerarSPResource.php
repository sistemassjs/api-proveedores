<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProveedorPuedeGenerarSPResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'puede_generar_sp' => $this->resource['puede_generar_sp'],
            'detalle' => [
                'perfil_empresa_completo' => (bool) ($this->resource['detalle']['perfil_empresa_completo'] ?? false),
                'tiene_cuenta_bancaria' => (bool) ($this->resource['detalle']['tiene_cuenta_bancaria'] ?? false),
                'tiene_constancia_fiscal' => (bool) ($this->resource['detalle']['tiene_constancia_fiscal'] ?? false),
                'tiene_logo' => (bool) ($this->resource['detalle']['tiene_logo'] ?? false),
                'tiene_informacion_general_y_datos_fiscales' => (bool) ($this->resource['detalle']['tiene_informacion_general_y_datos_fiscales'] ?? false),
            ],
        ];
    }
}
