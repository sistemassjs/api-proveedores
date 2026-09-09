<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PasswordResetCompleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'password' => 'required|string|min:8|max:128|confirmed',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Falta el código de recuperación del enlace. Vuelve a abrir el enlace del correo o solicita uno nuevo.',
            'token.string' => 'El código de recuperación no es válido. Solicita un nuevo correo desde «Olvidé mi contraseña».',
            'password.required' => 'Debes escribir la nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La nueva contraseña es demasiado larga.',
            'password.confirmed' => 'La confirmación debe ser exactamente igual a la nueva contraseña.',
        ];
    }
}
