<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminCatalogoPublicoUpdateEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'empresa_actual' => ['required', 'string', 'max:100'],
            'empresa' => ['sometimes', 'string', 'max:100'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:500'],
            'mostrar_en_listado' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'empresa_actual.required' => 'Debe indicar la empresa a actualizar.',
            'empresa_actual.max' => 'El nombre de la empresa no debe exceder 100 caracteres.',
            'empresa.max' => 'El nombre de la empresa no debe exceder 100 caracteres.',
            'logo.max' => 'La URL del logo no debe exceder 500 caracteres.',
        ];
    }
}
