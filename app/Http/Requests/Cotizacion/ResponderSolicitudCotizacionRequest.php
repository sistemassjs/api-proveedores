<?php

namespace App\Http\Requests\Cotizacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResponderSolicitudCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'canales' => ['required', Rule::in(['email', 'whatsapp', 'ambos'])],
            'mensaje' => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'canales.required' => 'Debe indicar el canal de respuesta.',
            'canales.in' => 'El canal debe ser email, whatsapp o ambos.',
        ];
    }
}
