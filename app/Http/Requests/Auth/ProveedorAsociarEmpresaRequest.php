<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ProveedorAsociarEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permitir que cualquier usuario pueda enviar este request
        return true;
    }

    public function rules(): array
    {
        return [
            'telefono' => ['required', 'string', 'min:10', 'max:10'],
            'empresa_construcc_id' => ['required', 'integer'],
            'empresa_construcc_nombre' => ['nullable', 'string', 'max:255'],
            'usuario_construcc_id' => ['required', 'integer'],
            'usuario_construcc_nombre' => ['required', 'string', 'max:255'],
            'rfc' => ['nulleable', 'string', 'max:13'],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.min' => 'El teléfono debe contener exactamente 10 dígitos.',
            'telefono.max' => 'El teléfono debe contener exactamente 10 dígitos.',

            'empresa_construcc_id.required' => 'El identificador de la empresa constructora es obligatorio.',
            'empresa_construcc_id.exists' => 'La empresa constructora seleccionada no existe.',

            'usuario_construcc_id.required' => 'El identificador del usuario constructor es obligatorio.',
            'usuario_construcc_nombre.required' => 'El nombre del usuario constructor es obligatorio.',

            'rfc.max' => 'El RFC debe tener un máximo de 13 caracteres.',
        ];
    }
}
