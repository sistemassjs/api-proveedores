<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogoFamiliaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', Rule::unique('catalogo_familias', 'nombre')],
            'codigo' => ['nullable', 'string', 'max:50', Rule::unique('catalogo_familias', 'codigo')],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
