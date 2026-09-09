<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorRegistroBasicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permitir que cualquier usuario pueda enviar este request
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'telefono' => [
                'required', 
                'string', 
                'min:10', 
                'max:10',
                // NOTE: No validamos unique aquí porque la lógica de negocio
                // del controlador necesita verificar el tipo_alta del proveedor existente
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'token' => ['required', 'string'],
            'empresa_construcc_id' => ['nullable', 'integer'],
            'empresa_construcc_nombre' => ['nullable', 'string', 'max:255'],
            'usuario_construcc_id' => ['nullable', 'integer'],
            'usuario_construcc_nombre' => ['nullable', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'min:12', 'max:13'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_comercial.required' => 'El nombre comercial es obligatorio.',
            'nombre_comercial.max' => 'El nombre comercial no debe exceder los 255 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.min' => 'El teléfono debe tener 10 dígitos.',
            'telefono.max' => 'El teléfono debe tener 10 dígitos.',
            'telefono.unique' => 'Este teléfono ya está registrado en el sistema.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'token.required' => 'El token de invitación es obligatorio.',
        ];
    }
}
