<?php

namespace App\Http\Requests\Cotizacion;

use Illuminate\Foundation\Http\FormRequest;

class ProcesarSolicitudCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nota' => 'nullable|string|max:2000',
            'generar_pdf' => 'nullable|boolean',
        ];
    }
}
