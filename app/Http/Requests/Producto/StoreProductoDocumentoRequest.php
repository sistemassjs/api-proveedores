<?php

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['nullable', 'string', 'max:50'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'url' => ['required_without:archivo', 'nullable', 'string', 'max:500'],
            'archivo' => ['required_without:url', 'nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
