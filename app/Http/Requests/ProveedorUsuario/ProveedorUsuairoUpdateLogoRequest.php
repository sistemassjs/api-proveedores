<?php

namespace App\Http\Requests\ProveedorUsuario;

use Illuminate\Foundation\Http\FormRequest;

class ProveedorUsuairoUpdateLogoRequest extends FormRequest
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
            'logo' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'El logo es requerido.',
            'logo.image' => 'El archivo debe ser una imagen válida.',
            'logo.mimes' => 'La imagen debe estar en formato JPG, PNG o WEBP.',
            'logo.max' => 'La imagen no debe pesar más de 2MB.',
        ];
    }
}
