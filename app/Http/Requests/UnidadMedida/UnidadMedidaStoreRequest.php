<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadMedidaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clave' => ['required', 'string', 'max:10'],
            'nombre' => ['required', 'string', 'max:100', Rule::unique('unidad_medidas', 'nombre')],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'clave.required' => 'La clave es obligatoria.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique' => 'Ya existe una unidad de medida con ese nombre.',
        ];
    }
}
