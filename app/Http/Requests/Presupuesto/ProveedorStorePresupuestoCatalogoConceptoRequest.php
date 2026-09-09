<?php

namespace App\Http\Requests\Presupuesto;

use App\Models\PresupuestoCatalogoConcepto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorStorePresupuestoCatalogoConceptoRequest extends FormRequest
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
            'descripcion' => [
                'required',
                'string',
                'max:'.PresupuestoCatalogoConcepto::DESCRIPCION_MAX,
            ],
            'categoria' => [
                'required',
                'string',
                Rule::in(PresupuestoCatalogoConcepto::categoriasValidas()),
            ],
            'unidad' => ['required', 'string', 'max:50'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'imagen_path' => ['nullable', 'string', 'max:255'],
            'imagen_base64' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $this->validateImagenBase64($attribute, $value, $fail);
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
            'descripcion.required' => 'La descripción del concepto es obligatoria.',
            'descripcion.string' => 'La descripción del concepto debe ser texto.',
            'descripcion.max' => 'La descripción del concepto no debe exceder '
                .PresupuestoCatalogoConcepto::DESCRIPCION_MAX.' caracteres.',
            'categoria.required' => 'La categoría del concepto es obligatoria.',
            'categoria.in' => 'La categoría debe ser producto o servicio.',
            'unidad.required' => 'La unidad del concepto es obligatoria.',
            'unidad.string' => 'La unidad del concepto debe ser texto.',
            'unidad.max' => 'La unidad del concepto no debe exceder 50 caracteres.',
            'precio_unitario.required' => 'El precio unitario del concepto es obligatorio.',
            'precio_unitario.numeric' => 'El precio unitario del concepto debe ser numérico.',
            'precio_unitario.min' => 'El precio unitario del concepto no puede ser negativo.',
        ];
    }

    private function validateImagenBase64(string $attribute, mixed $value, \Closure $fail): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        $matches = [];
        if (! is_string($value) || ! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/', $value, $matches)) {
            $fail('La imagen del concepto debe estar en formato JPG, JPEG, PNG o WEBP en base64.');

            return;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            $fail('La imagen del concepto no contiene un base64 válido.');

            return;
        }

        if (strlen($binary) > 5 * 1024 * 1024) {
            $fail('La imagen del concepto no debe superar 5 MB.');
        }
    }
}
