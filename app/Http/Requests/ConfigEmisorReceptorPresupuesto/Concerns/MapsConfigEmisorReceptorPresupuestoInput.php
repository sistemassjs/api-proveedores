<?php

namespace App\Http\Requests\ConfigEmisorReceptorPresupuesto\Concerns;

use App\Models\ConfigEmisorReceptorPresupuesto;

trait MapsConfigEmisorReceptorPresupuestoInput
{
    protected function prepareForValidation(): void
    {
        $nullableStrings = [
            'subfijo',
            'ape1',
            'ape2',
            'puesto',
            'telefono',
            'correo',
            'color_fondo',
        ];

        $merge = [];
        foreach ($nullableStrings as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function mapTipoToInt(?string $tipo): int
    {
        return $tipo === 'emisor'
            ? ConfigEmisorReceptorPresupuesto::TIPO_EMISOR
            : ConfigEmisorReceptorPresupuesto::TIPO_RECEPTOR;
    }

    protected function mapEstadoToInt(?string $estado): int
    {
        return match ($estado) {
            'inactivo' => ConfigEmisorReceptorPresupuesto::ESTADO_INACTIVO,
            'default' => ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT,
            default => ConfigEmisorReceptorPresupuesto::ESTADO_ACTIVO,
        };
    }
}
