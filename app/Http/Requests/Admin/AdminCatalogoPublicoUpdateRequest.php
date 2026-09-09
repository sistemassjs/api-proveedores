<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminCatalogoPublicoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'marca' => ['sometimes', 'nullable', 'string', 'max:255'],
            'categoria' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subcategoria' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unidad' => ['sometimes', 'nullable', 'string', 'max:50'],
            'modelo' => ['sometimes', 'nullable', 'string', 'max:100'],
            'empresa' => ['sometimes', 'string', 'max:255'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:500'],
            'imagen' => ['sometimes', 'nullable', 'string', 'max:500'],
            'precio_base' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'precio_mayoreo' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'precio_menudeo' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
            'mostrar_en_listado' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.string' => 'El nombre debe ser texto.',
            'empresa.string' => 'La empresa debe ser texto.',
            'precio_base.numeric' => 'El precio base debe ser numérico.',
            'precio_mayoreo.numeric' => 'El precio de mayoreo debe ser numérico.',
            'precio_menudeo.numeric' => 'El precio de menudeo debe ser numérico.',
        ];
    }
}
