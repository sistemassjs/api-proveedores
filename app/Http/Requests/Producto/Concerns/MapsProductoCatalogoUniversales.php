<?php

namespace App\Http\Requests\Producto\Concerns;

use App\Models\CatalogoSubfamilia;
use Illuminate\Validation\Rule;

trait MapsProductoCatalogoUniversales
{
    protected function reglasCamposUniversales(bool $sometimes = false): array
    {
        $prefix = $sometimes ? ['sometimes'] : [];

        return [
            'tipo' => array_merge($prefix, ['nullable', 'string', Rule::in(['producto', 'servicio', 'renta'])]),
            'modelo' => array_merge($prefix, ['nullable', 'string', 'max:60']),
            'codigo_fabricante' => array_merge($prefix, ['nullable', 'string', 'max:100']),
            'codigo_barras' => array_merge($prefix, ['nullable', 'string', 'max:100']),
            'presentacion' => array_merge($prefix, ['nullable', 'string', 'max:255']),
            'cantidad_contenida' => array_merge($prefix, ['nullable', 'numeric', 'min:0']),
            'unidad_contenido_id' => array_merge($prefix, ['nullable', 'integer', 'exists:unidad_medidas,id']),
            'unidad_base_id' => array_merge($prefix, ['nullable', 'integer', 'exists:unidad_medidas,id']),
            'factor_conversion' => array_merge($prefix, ['nullable', 'numeric', 'min:0']),
            'disponibilidad' => array_merge($prefix, ['nullable', 'string', 'max:50']),
            'tiempo_entrega' => array_merge($prefix, ['nullable', 'string', 'max:100']),
            'url_producto' => array_merge($prefix, ['nullable', 'string', 'max:500']),
            'tags' => array_merge($prefix, ['nullable', 'array']),
            'tags.*' => ['string', 'max:100'],
            'familia_id' => array_merge($prefix, ['nullable', 'integer', 'exists:catalogo_familias,id']),
            'subfamilia_id' => array_merge($prefix, [
                'nullable',
                'integer',
                'exists:catalogo_subfamilias,id',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $familiaId = $this->input('familia_id');
                    $sub = CatalogoSubfamilia::find($value);
                    if (! $sub) {
                        return;
                    }
                    if ($familiaId !== null && (int) $sub->familia_id !== (int) $familiaId) {
                        $fail('La subfamilia no pertenece a la familia indicada.');
                    }
                },
            ]),
            'especificaciones' => array_merge($prefix, ['nullable', 'array']),
            'especificaciones.*.atributo' => ['required_with:especificaciones', 'string', 'max:255'],
            'especificaciones.*.clave' => ['nullable', 'string', 'max:255'],
            'especificaciones.*.valor' => ['required_with:especificaciones', 'string'],
            'especificaciones.*.unidad' => ['nullable', 'string', 'max:50'],
            'especificaciones.*.orden' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
