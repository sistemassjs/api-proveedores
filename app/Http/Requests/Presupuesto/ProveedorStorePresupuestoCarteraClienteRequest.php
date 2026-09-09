<?php

namespace App\Http\Requests\Presupuesto;

use Illuminate\Foundation\Http\FormRequest;

class ProveedorStorePresupuestoCarteraClienteRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'puesto' => ['nullable', 'string', 'max:255'],
            'empresa' => ['required', 'string', 'max:255'],
            'alias_empresa' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:255'],
            'logo_base64' => ['nullable', 'string'],
            'eliminar_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del contacto es obligatorio.',
            'empresa.required' => 'El nombre de la empresa es obligatorio.',
            'correo.email' => 'Ingrese un correo válido.',
        ];
    }
}
