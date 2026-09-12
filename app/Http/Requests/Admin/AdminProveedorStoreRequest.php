<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminProveedorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_comercial' => 'required|string|max:255',
            'razon_social' => 'required|string|max:255',
            'rfc' => 'required|string|max:13|unique:proveedores,rfc',
            'email' => 'required|email|max:255|unique:proveedores,email',
            'telefono' => 'nullable|string|max:20',
            'is_proveedor_catalogo' => 'sometimes|boolean',
            'is_proveedor_sp' => 'sometimes|boolean',
        ];
    }
}
