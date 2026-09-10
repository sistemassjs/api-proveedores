<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogoSubfamiliaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $familiaId = $this->route('familia')?->id ?? $this->route('familia');

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('catalogo_subfamilias', 'nombre')->where(fn ($q) => $q->where('familia_id', $familiaId)),
            ],
            'codigo' => ['nullable', 'string', 'max:50'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
