<?php

namespace App\Http\Requests\Construcc;

use App\Models\PagoFactura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagoFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('monto') && is_numeric($this->input('monto'))) {
            $data['monto'] = abs((float) $this->input('monto'));
        }

        if ($this->has('metodo_pago') && is_string($this->input('metodo_pago'))) {
            $data['metodo_pago'] = strtoupper(trim($this->input('metodo_pago')));
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'folio_factura' => ['nullable', 'string', 'max:100'],
            'metodo_pago' => ['nullable', 'string', Rule::in([PagoFactura::METODO_PUE, PagoFactura::METODO_PPD])],
            'monto' => ['nullable', 'numeric', 'min:0.01'],
            'factura_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'factura_xml' => ['nullable', 'file', 'mimes:xml', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                ! $this->hasFile('factura_pdf')
                && ! $this->hasFile('factura_xml')
                && ! $this->filled('folio_factura')
                && ! $this->filled('metodo_pago')
                && ! $this->filled('monto')
            ) {
                $validator->errors()->add(
                    'factura',
                    'Debes enviar al menos un dato o archivo de la factura.'
                );
            }
        });
    }
}
