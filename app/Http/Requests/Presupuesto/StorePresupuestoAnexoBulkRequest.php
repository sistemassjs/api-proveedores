<?php

namespace App\Http\Requests\Presupuesto;

use Illuminate\Foundation\Http\FormRequest;

class StorePresupuestoAnexoBulkRequest extends FormRequest
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
            'anexos' => ['required', 'array', 'min:1', 'max:30'],
            'anexos.*.titulo' => ['nullable', 'string', 'max:40'],
            'anexos.*.descripcion' => ['nullable', 'string', 'max:100'],
            'anexos.*.precio' => ['nullable', 'numeric', 'min:0'],
            'anexos.*.orden' => ['nullable', 'integer', 'min:1'],
            'anexos.*.archivo_base64' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $this->validateBase64Image($attribute, $value, $fail);
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
            'anexos.required' => 'Debe enviar al menos un anexo.',
            'anexos.array' => 'El formato de anexos no es válido.',
            'anexos.min' => 'Debe enviar al menos un anexo.',
            'anexos.max' => 'No puede enviar más de 30 anexos en una sola operación.',
            'anexos.*.titulo.string' => 'El título del anexo debe ser texto.',
            'anexos.*.titulo.max' => 'El título del anexo no debe exceder 40 caracteres.',
            'anexos.*.descripcion.string' => 'La descripción del anexo debe ser texto.',
            'anexos.*.descripcion.max' => 'La descripción del anexo no debe exceder 100 caracteres.',
            'anexos.*.precio.numeric' => 'El precio del anexo debe ser numérico.',
            'anexos.*.precio.min' => 'El precio del anexo no puede ser menor a 0.',
            'anexos.*.orden.integer' => 'El orden del anexo debe ser un número entero.',
            'anexos.*.orden.min' => 'El orden del anexo debe ser mayor a 0.',
            'anexos.*.archivo_base64.required' => 'La imagen del anexo es obligatoria.',
            'anexos.*.archivo_base64.string' => 'La imagen del anexo debe enviarse como texto base64.',
        ];
    }

    private function validateBase64Image(string $attribute, mixed $value, \Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('La imagen del anexo es obligatoria.');

            return;
        }

        $matches = [];
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/', $value, $matches)) {
            $fail('La imagen del anexo debe estar en formato JPG, JPEG, PNG o WEBP en base64.');

            return;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            $fail('La imagen del anexo no contiene un base64 válido.');

            return;
        }

        if (strlen($binary) > 5 * 1024 * 1024) {
            $fail('La imagen del anexo no debe superar 5 MB.');
        }
    }
}
