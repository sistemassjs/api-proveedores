<?php

namespace App\Http\Requests\Producto\Concerns;

use App\Models\CatalogoSubfamilia;
use Illuminate\Validation\Rule;

trait MapsProductoCatalogoUniversales
{
    /**
     * '' / 'null' / espacios → null en precios (evita guardar strings vacíos).
     */
    protected function normalizePreciosInput(): void
    {
        $payload = $this->all();
        $changed = false;

        foreach (['precio_base', 'precio_mayoreo', 'precio_menudeo'] as $campo) {
            if (! array_key_exists($campo, $payload)) {
                continue;
            }
            $payload[$campo] = $this->normalizePrecioValue($payload[$campo]);
            $changed = true;
        }

        if ($changed) {
            $this->merge($payload);
        }
    }

    protected function normalizePrecioValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || strtolower($trimmed) === 'null') {
                return null;
            }

            return $trimmed;
        }

        return $value;
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule|\Closure>>
     */
    protected function reglasPrecios(bool $sometimes = false): array
    {
        $rules = ['nullable', 'numeric', 'min:0'];

        return [
            'precio_base' => $sometimes ? array_merge(['sometimes'], $rules) : $rules,
            'precio_mayoreo' => $sometimes ? array_merge(['sometimes'], $rules) : $rules,
            'precio_menudeo' => $sometimes ? array_merge(['sometimes'], $rules) : $rules,
        ];
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule|\Closure>>
     */
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
            'familia_id' => array_merge($prefix, [
                'nullable',
                'integer',
                'exists:catalogo_familias,id',
                Rule::requiredIf(fn () => $this->filled('subfamilia_id')),
            ]),
            'subfamilia_id' => array_merge($prefix, [
                'nullable',
                'integer',
                'exists:catalogo_subfamilias,id',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $familiaId = $this->input('familia_id');
                    if ($familiaId === null || $familiaId === '') {
                        return;
                    }
                    $sub = CatalogoSubfamilia::find($value);
                    if (! $sub) {
                        return;
                    }
                    if ((int) $sub->familia_id !== (int) $familiaId) {
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
            'mostrar_en_catalogo_publico' => array_merge($prefix, ['nullable', 'boolean']),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesCamposUniversales(): array
    {
        return [
            'tipo.in' => 'El tipo debe ser producto, servicio o renta.',
            'modelo.max' => 'El modelo no puede superar 60 caracteres.',
            'codigo_fabricante.max' => 'El código de fabricante no puede superar 100 caracteres.',
            'codigo_barras.max' => 'El código de barras no puede superar 100 caracteres.',
            'presentacion.max' => 'La presentación no puede superar 255 caracteres.',
            'cantidad_contenida.numeric' => 'La cantidad contenida debe ser un número.',
            'cantidad_contenida.min' => 'La cantidad contenida no puede ser negativa.',
            'unidad_contenido_id.exists' => 'La unidad de contenido seleccionada no es válida.',
            'unidad_base_id.exists' => 'La unidad base seleccionada no es válida.',
            'factor_conversion.numeric' => 'El factor de conversión debe ser un número.',
            'factor_conversion.min' => 'El factor de conversión no puede ser negativo.',
            'disponibilidad.max' => 'La disponibilidad no puede superar 50 caracteres.',
            'tiempo_entrega.max' => 'El tiempo de entrega no puede superar 100 caracteres.',
            'url_producto.max' => 'La URL del producto no puede superar 500 caracteres.',
            'tags.array' => 'Los tags deben enviarse como lista.',
            'tags.*.string' => 'Cada tag debe ser texto.',
            'tags.*.max' => 'Cada tag no puede superar 100 caracteres.',
            'familia_id.required' => 'La familia es obligatoria cuando se indica subfamilia.',
            'familia_id.exists' => 'La familia OPUS seleccionada no es válida.',
            'familia_id.integer' => 'La familia OPUS debe ser un identificador numérico.',
            'subfamilia_id.exists' => 'La subfamilia OPUS seleccionada no es válida.',
            'subfamilia_id.integer' => 'La subfamilia OPUS debe ser un identificador numérico.',
            'especificaciones.array' => 'Las especificaciones deben enviarse como lista.',
            'especificaciones.*.atributo.required_with' => 'Cada especificación requiere un atributo.',
            'especificaciones.*.atributo.max' => 'El atributo de la especificación no puede superar 255 caracteres.',
            'especificaciones.*.valor.required_with' => 'Cada especificación requiere un valor.',
            'especificaciones.*.unidad.max' => 'La unidad de la especificación no puede superar 50 caracteres.',
            'especificaciones.*.orden.integer' => 'El orden de la especificación debe ser un entero.',
            'especificaciones.*.orden.min' => 'El orden de la especificación no puede ser negativo.',
            'mostrar_en_catalogo_publico.boolean' => 'El indicador de catálogo público debe ser verdadero o falso.',
            'precio_base.numeric' => 'El precio base debe ser un número.',
            'precio_base.min' => 'El precio base no puede ser negativo.',
            'precio_mayoreo.numeric' => 'El precio de mayoreo debe ser un número.',
            'precio_mayoreo.min' => 'El precio de mayoreo no puede ser negativo.',
            'precio_menudeo.numeric' => 'El precio de menudeo debe ser un número.',
            'precio_menudeo.min' => 'El precio de menudeo no puede ser negativo.',
        ];
    }
}
