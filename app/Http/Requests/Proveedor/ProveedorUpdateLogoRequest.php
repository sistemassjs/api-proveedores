<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ProveedorUpdateLogoRequest",
 *     required={
 *         "logo"
 *     },
 *
 *     @OA\Property(property="logo", type="string", format="binary", example="logo.png"),
 * )
 */
class ProveedorUpdateLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg',
                // 'max:2048', // tamaño máximo en KB (2MB)
                // 'dimensions:min_width=200,min_height=200,max_width=1000,max_height=1000,ratio=1/1',
            ],
        ];
    }

    public function messages()
    {
        return [
            'logo.required' => 'Debes seleccionar un logo para continuar.',
            'logo.image' => 'El archivo seleccionado no es una imagen válida.',
            'logo.mimes' => 'El logo debe estar en formato JPG o PNG.',
            // 'logo.max' => 'La imagen no debe pesar más de 2MB.',
            // 'logo.dimensions' => 'La imagen debe tener entre 200x200px y 1000x1000px, y ser cuadrada (relación 1:1).',
        ];
    }
}
