<?php

namespace App\Http\Requests\Producto;

use App\Http\Requests\Producto\Concerns\MapsProductoCatalogoUniversales;
use App\Models\Categoria;
use App\Models\Marca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoUpdateRequest extends FormRequest
{
    use MapsProductoCatalogoUniversales;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePreciosInput();
    }

    public function rules(): array
    {
        $proveedorId = $this->route('proveedor')->id ?? $this->input('proveedor_id');

        return array_merge([
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'codigo_interno' => ['sometimes', 'required', 'string', 'max:50'],
            'proveedor_id' => ['sometimes', 'required', 'integer', 'exists:proveedores,id'],

            'unidad_medida_id' => ['sometimes', 'nullable', 'integer', 'exists:unidad_medidas,id'],

            'categoria_id' => [
                Rule::requiredIf(fn () => $this->filled('subcategoria_id')),
                'nullable',
                'integer',
                'exists:categorias,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $categoria = Categoria::delProveedor($proveedorId)->find($value);

                    if (! $categoria) {
                        return $fail('La categoría no pertenece al proveedor.');
                    }

                    if ((int) $categoria->nivel !== 0) {
                        return $fail('La categoría debe estar en el nivel 0 (categoría padre).');
                    }
                },
            ],

            'subcategoria_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categorias,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $subcategoria = Categoria::with('parent')->delProveedor($proveedorId)->find($value);

                    if (! $subcategoria) {
                        return $fail('La subcategoría no pertenece al proveedor.');
                    }

                    if (! $subcategoria->parent_id) {
                        return $fail('La subcategoría seleccionada no es válida o no pertenece a una categoría padre.');
                    }

                    $categoriaId = $this->input('categoria_id');
                    if ($categoriaId !== null && $categoriaId !== '' && (int) $subcategoria->parent_id !== (int) $categoriaId) {
                        return $fail('La subcategoría no pertenece a la categoría indicada.');
                    }
                },
            ],

            'marca_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:marcas,id',
                function ($attribute, $value, $fail) use ($proveedorId) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (! Marca::where('id', $value)->where('proveedor_id', $proveedorId)->exists()) {
                        $fail('La marca seleccionada no pertenece a este proveedor.');
                    }
                },
            ],
        ], $this->reglasPrecios(true), $this->reglasCamposUniversales(true));
    }

    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser texto.',
            'nombre.max' => 'El nombre no puede superar 100 caracteres.',
            'descripcion.string' => 'La descripción debe ser texto.',
            'descripcion.max' => 'La descripción no puede superar 255 caracteres.',
            'codigo_interno.required' => 'El código interno es obligatorio.',
            'codigo_interno.string' => 'El código interno debe ser texto.',
            'codigo_interno.max' => 'El código interno no puede superar 50 caracteres.',
            'proveedor_id.required' => 'El proveedor es obligatorio.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido.',
            'proveedor_id.integer' => 'El proveedor debe ser un identificador numérico.',
            'unidad_medida_id.integer' => 'La unidad de medida debe ser un identificador numérico.',
            'unidad_medida_id.exists' => 'La unidad de medida seleccionada no es válida.',
            'categoria_id.required' => 'La categoría es obligatoria cuando se indica subcategoría.',
            'categoria_id.integer' => 'La categoría debe ser un identificador numérico.',
            'categoria_id.exists' => 'La categoría seleccionada no es válida.',
            'subcategoria_id.integer' => 'La subcategoría debe ser un identificador numérico.',
            'subcategoria_id.exists' => 'La subcategoría seleccionada no es válida.',
            'marca_id.integer' => 'La marca debe ser un identificador numérico.',
            'marca_id.exists' => 'La marca seleccionada no es válida.',
        ], $this->mensajesCamposUniversales());
    }
}
