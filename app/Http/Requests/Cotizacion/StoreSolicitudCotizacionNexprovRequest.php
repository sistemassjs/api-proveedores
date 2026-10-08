<?php

namespace App\Http\Requests\Cotizacion;

use App\Models\SolicitudCotizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSolicitudCotizacionNexprovRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origen' => [
                'nullable',
                Rule::in([
                    SolicitudCotizacion::ORIGEN_NEXPROV,
                    SolicitudCotizacion::ORIGEN_EMPRESA_TERCERO,
                ]),
            ],
            'cliente_nombre' => 'required|string|max:255',
            'cliente_email' => 'required|email|max:255',
            'cliente_telefono' => 'nullable|string|max:40',
            'cliente_whatsapp' => 'nullable|string|max:40',
            'cliente_notas' => 'nullable|string|max:2000',
            'solicitante_empresa' => 'nullable|string|max:255',
            'observaciones_internas' => 'nullable|string|max:5000',
            'vigencia_hasta' => 'nullable|date',
            'politicas' => 'nullable|string|max:10000',
            'detalles' => 'nullable|array|max:200',
            'detalles.*.producto_id' => 'nullable|integer|exists:productos,id',
            'detalles.*.nombre' => 'nullable|string|max:255',
            'detalles.*.descripcion' => 'nullable|string|max:5000',
            'detalles.*.unidad' => 'nullable|string|max:50',
            'detalles.*.codigo' => 'nullable|string|max:100',
            'detalles.*.precio_referencia' => 'nullable|numeric|min:0',
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0',
            'detalles.*.cantidad' => 'nullable|numeric|min:0.001|max:999999',
            'detalles.*.es_sugerencia_empresa' => 'nullable|boolean',
            'detalles.*.motivo_sugerencia' => 'nullable|string|max:255',
            'detalles.*.observaciones_linea' => 'nullable|string|max:500',
            'detalles.*.orden' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_nombre.required' => 'El nombre del cliente es obligatorio.',
            'cliente_email.required' => 'El correo del cliente es obligatorio.',
            'cliente_email.email' => 'El correo del cliente no es válido.',
        ];
    }
}
