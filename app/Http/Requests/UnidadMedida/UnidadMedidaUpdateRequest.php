<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadMedidaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadId = $this->route('unidad') ?? $this->route('unidadId') ?? $this->route('id');

        return [
            'clave' => ['required', 'string', 'max:10'],
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('unidad_medidas', 'nombre')->ignore($unidadId),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estatus' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'clave' => 'clave',
            'nombre' => 'nombre de unidad',
            'descripcion' => 'descripción',
            'activo' => 'estado activo',
        ];
    }
}
