<?php

namespace App\Http\Requests\Producto;

use App\Http\Requests\Producto\Concerns\MapsProductoCatalogoUniversales;
use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;

class ProductoStoreRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['required', 'string', 'max:255'],
            'codigo_interno' => ['required', 'string', 'max:50'],
            'proveedor_id' => ['sometimes', 'required', 'integer', 'exists:proveedores,id'],

            'precio_base' => ['sometimes', 'numeric'],
            'precio_mayoreo' => ['sometimes', 'numeric'],
            'precio_menudeo' => ['sometimes', 'numeric'],

            'unidad_medida_id' => ['required', 'integer', 'exists:unidad_medidas,id'],

            'categoria_id' => [
                'required',
                'integer',
                'exists:categorias,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    $categoria = Categoria::delProveedor($proveedorId)->find($value);

                    if (! $categoria) {
                        return $fail('La categoría padre no pertenece al proveedor.');
                    }

                    if ($categoria->nivel !== 0) {
                        return $fail('La categoría padre debe estar en el nivel 0.');
                    }
                },
            ],
            'subcategoria_id' => [
                'required',
                'integer',
                'exists:categorias,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    $subcategoria = Categoria::with('parent')->delProveedor($proveedorId)->find($value);

                    if (! $subcategoria) {
                        return $fail('La subcategoría no pertenece al proveedor.');
                    }

                    $padre = $subcategoria->parent;
                    $categoriaId = $this->input('categoria_id');

                    if (! $padre || $padre->id != $categoriaId) {
                        return $fail('La subcategoría no pertenece a la categoría padre especificada.');
                    }
                },
            ],

            'marca_id' => [
                'required',
                'integer',
                'exists:marcas,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    if (! \App\Models\Marca::where('id', $value)->where('proveedor_id', $proveedorId)->exists()) {
                        $fail('La marca seleccionada no pertenece a este proveedor.');
                    }
                },
            ],
        ], $this->reglasCamposUniversales(false));
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'codigo_interno.required' => 'El código interno es obligatorio.',
            'proveedor_id.required' => 'El proveedor es obligatorio.',
            'unidad_medida_id.required' => 'La unidad de medida es obligatoria.',
            'categoria_id.required' => 'La categoría padre es obligatoria.',
            'marca_id.required' => 'La marca es obligatoria.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido.',
            'unidad_medida_id.exists' => 'La unidad de medida seleccionada no es válida.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
            'marca_id.exists' => 'La marca seleccionada no es válida.',
            'precio_base.numeric' => 'El precio base debe ser un número.',
            'precio_mayoreo.numeric' => 'El precio de mayoreo debe ser un número.',
            'precio_menudeo.numeric' => 'El precio de menudeo debe ser un número.',
        ];
    }
}
