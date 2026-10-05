<?php

namespace App\Http\Requests\Cotizacion;

use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitudCotizacionPublicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_nombre' => 'required|string|max:255',
            'cliente_email' => 'required|email|max:255',
            'cliente_telefono' => 'nullable|string|max:40',
            'cliente_whatsapp' => 'nullable|string|max:40',
            'cliente_notas' => 'nullable|string|max:2000',
            'detalles' => 'required|array|min:1|max:100',
            'detalles.*.producto_id' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.001|max:999999',
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0',
            'detalles.*.observaciones_linea' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_nombre.required' => 'El nombre es obligatorio.',
            'cliente_email.required' => 'El correo es obligatorio.',
            'cliente_email.email' => 'El correo no es válido.',
            'detalles.required' => 'Debe incluir al menos un producto.',
            'detalles.min' => 'Debe incluir al menos un producto.',
            'detalles.*.producto_id.required' => 'Cada línea requiere un producto.',
            'detalles.*.cantidad.required' => 'Cada línea requiere cantidad.',
        ];
    }
}
