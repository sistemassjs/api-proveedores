<?php

namespace App\Http\Requests\CuentaBancaria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CuentaBancariaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['cuenta', 'clabe', 'tarjeta'] as $field) {
            if ($this->has($field) && $this->$field === '') {
                $merge[$field] = null;
            }
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'min:3', 'max:50'],
            'titular_cuenta' => ['required', 'string', 'min:2', 'max:100'],
            'banco_clave' => ['required', 'string', 'min:3', 'max:10'],
            'banco_nombre' => ['required', 'string', 'min:3', 'max:50'],
            // Nota: longitud de cuenta libre (sin rango fijo); solo dígitos.
            'cuenta' => [
                'required_if:clabe,*',
                'nullable',
                'string',
                'regex:/^\d+$/',
            ],
            'clabe' => ['nullable', 'string', 'size:18', 'regex:/^\d+$/'],
            'tarjeta' => ['nullable', 'string', 'size:16', 'regex:/^\d+$/'],
            'referencia' => ['nullable', 'string', 'max:50'],
            'sucursal' => ['nullable', 'string', 'max:100'],
            'swift' => ['nullable', 'string', 'min:8', 'max:11', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'preferida' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $cuenta = $this->input('cuenta');
            $clabe = $this->input('clabe');
            $tarjeta = $this->input('tarjeta');

            if (empty($cuenta) && empty($clabe) && empty($tarjeta)) {
                $validator->errors()->add('cuenta', 'Debe ingresar al menos uno: número de cuenta, CLABE o tarjeta.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'alias.required' => 'El alias de la cuenta es obligatorio.',
            'alias.min' => 'El alias debe tener al menos :min caracteres.',
            'alias.max' => 'El alias no puede exceder :max caracteres.',

            'titular_cuenta.required' => 'El nombre del titular es obligatorio.',
            'titular_cuenta.min' => 'El nombre del titular debe tener al menos :min caracteres.',
            'titular_cuenta.max' => 'El nombre del titular no puede exceder :max caracteres.',

            'banco_clave.required' => 'La clave del banco es obligatoria.',
            'banco_clave.min' => 'La clave del banco debe tener al menos :min caracteres.',
            'banco_clave.max' => 'La clave del banco no puede exceder :max caracteres.',

            'banco_nombre.required' => 'El nombre del banco es obligatorio.',
            'banco_nombre.min' => 'El nombre del banco debe tener al menos :min caracteres.',
            'banco_nombre.max' => 'El nombre del banco no puede exceder :max caracteres.',

            'cuenta.required_if' => 'El número de cuenta es obligatorio cuando se ingresa CLABE.',
            'cuenta.regex' => 'La cuenta debe contener solo números.',

            'clabe.size' => 'La CLABE debe tener exactamente 18 dígitos.',
            'clabe.regex' => 'La CLABE debe contener solo números.',

            'tarjeta.size' => 'La tarjeta debe tener exactamente 16 dígitos.',
            'tarjeta.regex' => 'La tarjeta debe contener solo números.',

            'referencia.string' => 'La referencia debe ser texto.',
            'referencia.max' => 'La referencia no puede exceder :max caracteres.',

            'sucursal.string' => 'La sucursal debe ser texto.',
            'sucursal.max' => 'La sucursal no puede exceder :max caracteres.',

            'swift.string' => 'El código SWIFT debe ser texto.',
            'swift.min' => 'El código SWIFT debe tener al menos :min caracteres.',
            'swift.max' => 'El código SWIFT no puede exceder :max caracteres.',
            'swift.regex' => 'El código SWIFT tiene un formato inválido. Debe seguir el formato internacional (ej. BBUXMXMM).',
        ];
    }
}
