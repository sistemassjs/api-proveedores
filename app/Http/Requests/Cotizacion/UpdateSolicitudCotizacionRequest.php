<?php

namespace App\Http\Requests\Cotizacion;

use App\Enums\EstadoSolicitudCotizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSolicitudCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_nombre' => 'sometimes|string|max:255',
            'cliente_email' => 'sometimes|email|max:255',
            'cliente_telefono' => 'nullable|string|max:40',
            'cliente_whatsapp' => 'nullable|string|max:40',
            'cliente_notas' => 'nullable|string|max:2000',
            'observaciones_internas' => 'nullable|string|max:5000',
            'estatus' => ['sometimes', Rule::in(EstadoSolicitudCotizacion::values())],
            'detalles' => 'sometimes|array|min:1|max:200',
            'detalles.*.id' => 'nullable|integer|exists:solicitud_cotizacion_detalles,id',
            'detalles.*.producto_id' => 'nullable|integer|exists:productos,id',
            'detalles.*.nombre' => 'nullable|string|max:255',
            'detalles.*.descripcion' => 'nullable|string|max:5000',
            'detalles.*.unidad' => 'nullable|string|max:50',
            'detalles.*.codigo' => 'nullable|string|max:100',
            'detalles.*.imagen' => 'nullable|string|max:500',
            'detalles.*.precio_referencia' => 'nullable|numeric|min:0',
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0',
            'detalles.*.cantidad' => 'nullable|numeric|min:0.001|max:999999',
            'detalles.*.es_sugerencia_empresa' => 'nullable|boolean',
            'detalles.*.motivo_sugerencia' => 'nullable|string|max:255',
            'detalles.*.observaciones_linea' => 'nullable|string|max:500',
            'detalles.*.orden' => 'nullable|integer|min:0',
            'detalles.*.eliminar' => 'nullable|boolean',
        ];
    }
}
