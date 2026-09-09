<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccCuentaBancariaResource extends JsonResource
{
    public function toArray($request)
    {
        $tipoDerivado = $this->clabe ? 'clabe' : ($this->cuenta ? 'cuenta' : 'tarjeta');

        return [
            // ----------------------------
            // Sección: Identificación
            // ----------------------------
            'identificacion' => [
                'id' => $this->id,
                'alias' => $this->alias,
                'tipo_cuenta' => $tipoDerivado,
                'preferida' => $this->preferida,
            ],

            // ----------------------------
            // Sección: Banco
            // ----------------------------
            'banco' => [
                'banco_clave' => $this->banco_clave,
                'banco_nombre' => $this->banco_nombre,
                'sucursal' => $this->sucursal,
                'swift' => $this->swift,
            ],

            // ----------------------------
            // Sección: Propietario
            // ----------------------------
            'propietario' => [
                'titular_cuenta' => $this->titular_cuenta,
                'referencia' => $this->referencia,
                'proveedor_id' => $this->proveedor_id,
            ],

            // ----------------------------
            // Sección: Datos de pago
            // ----------------------------
            'datos_pago' => [
                'cuenta' => $this->cuenta,
                'clabe_interbancaria' => $this->clabe,
                'tarjeta' => $this->tarjeta,
            ],
        ];
    }
}
