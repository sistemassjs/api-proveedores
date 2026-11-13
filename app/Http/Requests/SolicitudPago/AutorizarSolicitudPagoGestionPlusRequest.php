<?php

namespace App\Http\Requests\SolicitudPago;

use Illuminate\Foundation\Http\FormRequest;

class AutorizarSolicitudPagoGestionPlusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notas' => 'nullable|string|max:1000',
            'monto_autorizado' => 'nullable|numeric|min:0.01',
        ];
    }
}
