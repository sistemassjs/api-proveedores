<?php

namespace App\Http\Requests\Construcc;

use App\Models\PagoFactura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConstruccPagosSPPRegistrarPagoDirectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('monto_total') && is_numeric($this->input('monto_total'))) {
            $data['monto_total'] = abs((float) $this->input('monto_total'));
        }

        if ($this->has('info_comprobante.monto') && is_numeric($this->input('info_comprobante.monto'))) {
            $info = $this->input('info_comprobante', []);
            $info['monto'] = abs((float) $info['monto']);
            $data['info_comprobante'] = $info;
        }

        if (is_array($this->input('facturas'))) {
            $data['facturas'] = collect($this->input('facturas'))
                ->map(function ($factura) {
                    if (isset($factura['monto']) && is_numeric($factura['monto'])) {
                        $factura['monto'] = abs((float) $factura['monto']);
                    }
                    if (isset($factura['metodo_pago']) && is_string($factura['metodo_pago'])) {
                        $factura['metodo_pago'] = strtoupper(trim($factura['metodo_pago']));
                    }

                    return $factura;
                })
                ->all();
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'comprobante_pago' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],

            'cuenta_bancaria_empresa_construcc_id' => ['nullable', 'numeric'],
            'cuenta_destino_id' => ['nullable', 'integer'],
            'monto_total' => ['required', 'numeric', 'min:0.01'],

            'empresa_id' => ['required', 'integer'],
            'proveedor_id' => ['required', 'integer'],
            'usuario_id' => ['required', 'integer'],
            'usuario_nombre' => ['required', 'string', 'max:255'],

            'info_comprobante' => ['nullable', 'array'],
            'info_comprobante.monto' => ['nullable', 'numeric', 'min:0.01'],
            'info_comprobante.fecha' => ['nullable', 'date'],
            'info_comprobante.hora' => ['nullable', 'string'],
            'info_comprobante.referencia' => ['nullable', 'string', 'max:50'],
            'info_comprobante.bancoDestino' => ['nullable', 'string', 'max:255'],
            'info_comprobante.nombreBeneficiario' => ['nullable', 'string', 'max:255'],
            'info_comprobante.claveRastreo' => ['nullable', 'string', 'max:255'],

            'observaciones' => ['nullable', 'string'],

            'facturas' => ['nullable', 'array'],
            'facturas.*.folio_factura' => ['nullable', 'string', 'max:100'],
            'facturas.*.metodo_pago' => ['nullable', 'string', Rule::in([PagoFactura::METODO_PUE, PagoFactura::METODO_PPD])],
            'facturas.*.monto' => ['nullable', 'numeric', 'min:0.01'],
            'facturas.*.factura_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'facturas.*.factura_xml' => ['nullable', 'file', 'mimes:xml', 'max:10240'],

            'facturas.*.complementos' => ['nullable', 'array'],
            'facturas.*.complementos.*.folio_complemento' => ['nullable', 'string', 'max:100'],
            'facturas.*.complementos.*.complemento_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'facturas.*.complementos.*.complemento_xml' => ['nullable', 'file', 'mimes:xml', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'comprobante_pago.required' => 'Debes subir el comprobante de pago.',
            'comprobante_pago.mimes' => 'El comprobante debe ser PDF, JPG o PNG.',
            'monto_total.required' => 'Debes indicar el monto total del pago.',
            'empresa_id.required' => 'No se recibió la empresa.',
            'proveedor_id.required' => 'No se recibió el proveedor.',
            'facturas.*.metodo_pago.in' => 'El método de pago de la factura debe ser PUE o PPD.',
        ];
    }
}
