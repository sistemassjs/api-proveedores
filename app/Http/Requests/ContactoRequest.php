<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Ruta pública, no requiere autorización
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'empresa' => 'nullable|string|max:255',
            'mensaje' => 'nullable|string|max:2000',
            'files' => 'sometimes|array',
            'files.*' => 'file|max:5120', // 5MB por archivo (opcional)
        ];
    }

    /**
     * 
     * Get custom messages for validator errors.|
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'Debe proporcionar un correo electrónico válido',
            'email.max' => 'El correo electrónico no puede exceder 255 caracteres',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres',
            'empresa.max' => 'El nombre de la empresa no puede exceder 255 caracteres',
            'mensaje.required' => 'El mensaje es obligatorio',
            'mensaje.max' => 'El mensaje no puede exceder 2000 caracteres',
        ];
    }
}
