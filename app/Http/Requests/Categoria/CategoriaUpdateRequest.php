<?php

namespace App\Http\Requests\Categoria;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="CategoriaUpdateRequest",
 *     required={"nombre", "descripcion"},
 *
 *     @OA\Property(property="nombre", type="string", example="Catálogo actualizado"),
 *     @OA\Property(property="descripcion", type="string", example="Nueva descripción del catálogo"),
 *     @OA\Property(property="categoria_padre_id", type="integer", example=2),
 * )
 */
class CategoriaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proveedorId = $this->route('proveedor')?->id;
        $categoriaId = (int) $this->route('categoria');

        return [
            'nombre' => ['sometimes', 'string', 'max:60'],
            'descripcion' => ['sometimes', 'string', 'max:255'],
            'categoria_padre_id' => [
                'sometimes',
                'nullable',
                'integer',
                'different:categoria_id',
                function ($attribute, $value, $fail) use ($proveedorId, $categoriaId) {
                    if ($value === null) {
                        return;
                    }

                    if ((int) $value === $categoriaId) {
                        $fail('Una categoría no puede ser su propia categoría padre.');
                        return;
                    }

                    if (! Categoria::where('id', $value)
                        ->where('proveedor_id', $proveedorId)
                        ->where('nivel', '<', 2)
                        ->exists()) {
                        $fail('La categoría padre no es válida o no pertenece a este proveedor.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'proveedor_id.required' => 'El proveedor es obligatorio.',
            'categoria_padre_id.exists' => 'La categroia seleccionado no es válido.',
        ];
    }
}
