<?php

namespace App\Http\Requests\SolicitudPago;

use Illuminate\Foundation\Http\FormRequest;

class PagarSolicitudPagoGestionPlusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_pago' => 'required|numeric|min:0.01',
            'comprobante_pago' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'fecha_pago' => 'nullable|date',
            'referencia_pago' => 'nullable|string|max:255',
            'clave_rastreo' => 'nullable|string|max:255',
            'banco_destino' => 'nullable|string|max:255',
            'titular_cuenta_destino' => 'nullable|string|max:255',
            'cuenta_destino_id' => 'nullable|integer|exists:cuentas_bancarias,id',
            'observaciones' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'monto_pago.required' => 'El monto del pago es obligatorio.',
            'comprobante_pago.required' => 'El comprobante de pago es obligatorio.',
            'comprobante_pago.mimes' => 'El comprobante debe ser PDF o imagen (jpg, jpeg, png).',
        ];
    }
}
