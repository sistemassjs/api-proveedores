<?php

namespace App\Http\Requests\Presupuesto;

use App\Support\PresupuestoAnexoPdfStorage;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePresupuestoAnexoPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:40'],
            'orden' => ['nullable', 'integer', 'min:1'],
            'mostrar_estampado' => ['nullable', 'boolean'],
            'mostrar_numero_pagina' => ['nullable', 'boolean'],
            'mostrar_datos_presupuesto' => ['nullable', 'boolean'],
            'archivo_base64' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $this->validateBase64Pdf($attribute, $value, $fail);
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.string' => 'El título del anexo PDF debe ser texto.',
            'titulo.max' => 'El título del anexo PDF no debe exceder 40 caracteres.',
            'orden.integer' => 'El orden del anexo PDF debe ser un número entero.',
            'orden.min' => 'El orden del anexo PDF debe ser mayor a 0.',
            'archivo_base64.string' => 'El PDF del anexo debe enviarse como texto base64.',
        ];
    }

    private function validateBase64Pdf(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            PresupuestoAnexoPdfStorage::decodificarPdfDesdeDataUri($value);
        } catch (\InvalidArgumentException $e) {
            $fail($e->getMessage());
        }
    }
}
