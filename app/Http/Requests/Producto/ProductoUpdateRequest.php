<?php

namespace App\Http\Requests\Producto;

use App\Http\Requests\Producto\Concerns\MapsProductoCatalogoUniversales;
use App\Models\Categoria;
use App\Models\Marca;
use Illuminate\Foundation\Http\FormRequest;

class ProductoUpdateRequest extends FormRequest
{
    use MapsProductoCatalogoUniversales;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proveedorId = $this->route('proveedor')->id ?? $this->input('proveedor_id');

        return array_merge([
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'descripcion' => ['sometimes', 'required', 'string', 'max:255'],
            'codigo_interno' => ['sometimes', 'required', 'string', 'max:50'],
            'proveedor_id' => ['sometimes', 'required', 'integer', 'exists:proveedores,id'],

            'precio_base' => ['sometimes', 'numeric'],
            'precio_mayoreo' => ['sometimes', 'numeric'],
            'precio_menudeo' => ['sometimes', 'numeric'],

            'unidad_medida_id' => ['sometimes', 'required', 'integer', 'exists:unidad_medidas,id'],

            'categoria_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:categorias,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    if (! Categoria::where('id', $value)->where('proveedor_id', $proveedorId)->exists()) {
                        $fail('La categoría seleccionada no pertenece a este proveedor.');
                    }
                },
            ],

            'subcategoria_id' => [
                'nullable',
                'integer',
                'exists:categorias,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    if (! Categoria::where('id', $value)
                        ->where('proveedor_id', $proveedorId)
                        ->whereNotNull('parent_id')
                        ->exists()) {
                        $fail('La subcategoría seleccionada no es válida o no pertenece a una categoría padre.');
                    }
                },
            ],

            'marca_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:marcas,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    if (! Marca::where('id', $value)->where('proveedor_id', $proveedorId)->exists()) {
                        $fail('La marca seleccionada no pertenece a este proveedor.');
                    }
                },
            ],
        ], $this->reglasCamposUniversales(true));
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'codigo_interno.required' => 'El código interno es obligatorio.',
            'proveedor_id.required' => 'El proveedor es obligatorio.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido.',
            'unidad_medida_id.required' => 'La unidad de medida es obligatoria.',
            'unidad_medida_id.exists' => 'La unidad de medida seleccionada no es válida.',
            'categoria_id.required' => 'La categoría es obligatoria.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
            'subcategoria_id.exists' => 'La subcategoría seleccionada no es válida.',
            'marca_id.required' => 'La marca es obligatoria.',
            'marca_id.exists' => 'La marca seleccionada no es válida.',
        ];
    }
}
