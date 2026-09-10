<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogoSubfamiliaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $familiaId = $this->route('familia')?->id ?? $this->route('familia');
        $subId = $this->route('subfamilia')?->id ?? $this->route('subfamilia');

        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('catalogo_subfamilias', 'nombre')
                    ->where(fn ($q) => $q->where('familia_id', $familiaId))
                    ->ignore($subId),
            ],
            'codigo' => ['nullable', 'string', 'max:50'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
