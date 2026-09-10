<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogoFamiliaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('familia')?->id ?? $this->route('familia');

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('catalogo_familias', 'nombre')->ignore($id)],
            'codigo' => ['nullable', 'string', 'max:50', Rule::unique('catalogo_familias', 'codigo')->ignore($id)],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
