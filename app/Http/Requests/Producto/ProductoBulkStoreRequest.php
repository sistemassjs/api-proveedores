<?php

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;

class ProductoBulkStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $productos = $this->input('productos');
        if (! is_array($productos)) {
            return;
        }

        foreach ($productos as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach (['precio_base', 'precio_mayoreo', 'precio_menudeo'] as $campo) {
                if (array_key_exists($campo, $row)) {
                    $productos[$i][$campo] = $this->normalizePrecioInput($row[$campo]);
                }
            }

            if (isset($row['precios']) && is_array($row['precios'])) {
                foreach (['precio_mayoreo', 'precio_menudeo', 'precio_base'] as $campo) {
                    if (array_key_exists($campo, $row['precios'])) {
                        $productos[$i]['precios'][$campo] = $this->normalizePrecioInput($row['precios'][$campo]);
                    }
                }
            }
        }

        $this->merge(['productos' => $productos]);
    }

    /**
     * '' / 'null' / espacios → null; conserva 0 y números >= 0.
     */
    private function normalizePrecioInput(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || strtolower($trimmed) === 'null') {
                return null;
            }
            $value = $trimmed;
        }

        return $value;
    }

    public function rules(): array
    {
        $precioRules = ['nullable', 'numeric', 'min:0'];

        return [
            'productos' => ['required', 'array', 'min:1', 'max:1000'],
            'archivo' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'metadata.importId' => ['nullable', 'string', 'max:100'],

            'productos.*.sku' => ['nullable', 'string', 'max:100'],
            'productos.*.codigo' => ['nullable', 'string', 'max:100'],
            'productos.*.codigo_interno' => ['nullable', 'string', 'max:100'],
            'productos.*.nombre' => ['required', 'string', 'max:255'],
            'productos.*.descripcion' => ['nullable', 'string'],
            'productos.*.modelo' => ['nullable', 'string', 'max:60'],
            'productos.*.precio_base' => $precioRules,
            'productos.*.precio_mayoreo' => $precioRules,
            'productos.*.precio_menudeo' => $precioRules,
            'productos.*.activo' => ['nullable', 'boolean'],
            'productos.*.stock' => ['nullable', 'integer', 'min:0'],
            'productos.*.stock_inicial' => ['nullable', 'integer', 'min:0'],

            'productos.*.categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'productos.*.categoria_nombre' => ['nullable', 'string', 'max:255'],
            'productos.*.subcategoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'productos.*.subcategoria_nombre' => ['nullable', 'string', 'max:255'],
            'productos.*.marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'productos.*.marca_nombre' => ['nullable', 'string', 'max:255'],
            'productos.*.unidad_medida_id' => ['nullable', 'integer', 'exists:unidad_medidas,id'],
            'productos.*.unidad_medida' => ['nullable', 'string', 'max:100'],

            'productos.*.familia_id' => ['nullable', 'integer', 'exists:catalogo_familias,id'],
            'productos.*.subfamilia_id' => ['nullable', 'integer', 'exists:catalogo_subfamilias,id'],
            'productos.*.familia' => ['nullable', 'string', 'max:255'],
            'productos.*.subfamilia' => ['nullable', 'string', 'max:255'],

            'productos.*.precios' => ['nullable', 'array'],
            'productos.*.precios.precio_base' => $precioRules,
            'productos.*.precios.precio_mayoreo' => $precioRules,
            'productos.*.precios.precio_menudeo' => $precioRules,

            'productos.*.especificaciones' => ['nullable', 'array'],
            'productos.*.especificaciones.*.atributo' => ['required_with:productos.*.especificaciones', 'string', 'max:255'],
            'productos.*.especificaciones.*.valor' => ['required_with:productos.*.especificaciones', 'string'],
            'productos.*.especificaciones.*.unidad' => ['nullable', 'string', 'max:50'],
            'productos.*.especificaciones.*.orden' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'productos.required' => 'Debe enviar al menos un producto.',
            'productos.*.nombre.required' => 'Cada producto debe tener nombre.',
            'productos.*.precio_base.min' => 'El precio base debe ser null, 0 o un número mayor a 0.',
            'productos.*.precio_mayoreo.min' => 'El precio mayoreo debe ser null, 0 o un número mayor a 0.',
            'productos.*.precio_menudeo.min' => 'El precio menudeo debe ser null, 0 o un número mayor a 0.',
            'productos.*.precios.precio_base.min' => 'El precio base debe ser null, 0 o un número mayor a 0.',
            'productos.*.precios.precio_mayoreo.min' => 'El precio mayoreo debe ser null, 0 o un número mayor a 0.',
            'productos.*.precios.precio_menudeo.min' => 'El precio menudeo debe ser null, 0 o un número mayor a 0.',
        ];
    }
}
