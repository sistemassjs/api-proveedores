<?php

namespace App\Http\Requests\SolicitudPago;

use Illuminate\Foundation\Http\FormRequest;

class RechazarSolicitudPagoGestionPlusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_rechazo' => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_rechazo.required' => 'El motivo de rechazo es obligatorio.',
            'motivo_rechazo.min' => 'El motivo de rechazo debe tener al menos 5 caracteres.',
        ];
    }
}
