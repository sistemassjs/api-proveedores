<?php

namespace App\Http\Requests\Construcc;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoComplementoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folio_complemento' => ['nullable', 'string', 'max:100'],
            'complemento_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'complemento_xml' => ['nullable', 'file', 'mimes:xml', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->hasFile('complemento_pdf') && ! $this->hasFile('complemento_xml')) {
                $validator->errors()->add(
                    'complemento',
                    'Debes subir al menos el PDF o el XML del complemento de pago.'
                );
            }
        });
    }
}
