<?php

namespace App\Http\Requests\Presupuesto;

use App\Models\PresupuestoCatalogoConcepto;
use App\Services\Presupuesto\PresupuestoMatrizCalculoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $proveedorId = (int) ($this->route('proveedor')?->id ?? $this->route('proveedor') ?? 0);

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
            'es_compuesto' => ['sometimes', 'boolean'],
            'clave' => [
                'nullable',
                'string',
                'max:'.PresupuestoCatalogoConcepto::CLAVE_MAX,
                Rule::unique('presupuesto_catalogo_conceptos', 'clave')
                    ->where(fn ($q) => $q->where('proveedor_id', $proveedorId)),
            ],
            'unidad' => ['required', 'string', 'max:50'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'imagen_path' => ['nullable', 'string', 'max:255'],
            'imagen_base64' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $this->validateImagenBase64($attribute, $value, $fail);
                },
            ],
            'componentes' => ['nullable', 'array'],
            'componentes.*.orden' => ['nullable', 'integer', 'min:0'],
            'componentes.*.categoria' => [
                'nullable',
                'string',
                Rule::in(PresupuestoCatalogoConcepto::categoriasValidas()),
            ],
            'componentes.*.catalogo_concepto_componente_id' => [
                'nullable',
                'integer',
                Rule::exists('presupuesto_catalogo_conceptos', 'id')
                    ->where(fn ($q) => $q->where('proveedor_id', $proveedorId)),
            ],
            'componentes.*.descripcion' => ['nullable', 'string', 'max:500'],
            'componentes.*.unidad' => ['nullable', 'string', 'max:50'],
            'componentes.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'componentes.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'componentes.*.clave' => ['nullable', 'string', 'max:40'],
            'componentes.*.clave_snapshot' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $data = $v->getData();
            $esCompuesto = filter_var($data['es_compuesto'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $componentes = $data['componentes'] ?? [];

            if ($esCompuesto) {
                if (! is_array($componentes) || count($componentes) < 1) {
                    $v->errors()->add(
                        'componentes',
                        'Un concepto compuesto requiere al menos un componente.'
                    );
                }

                return;
            }

            if (! array_key_exists('precio_unitario', $data) || $data['precio_unitario'] === null || $data['precio_unitario'] === '') {
                $v->errors()->add(
                    'precio_unitario',
                    'El precio unitario del concepto es obligatorio.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $clave = app(PresupuestoMatrizCalculoService::class)
            ->normalizarClave($this->input('clave'));

        $this->merge([
            'es_compuesto' => filter_var($this->input('es_compuesto', false), FILTER_VALIDATE_BOOLEAN),
            'clave' => $clave,
        ]);
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
            'precio_unitario.numeric' => 'El precio unitario del concepto debe ser numérico.',
            'precio_unitario.min' => 'El precio unitario del concepto no puede ser negativo.',
            'clave.unique' => 'Ya existe un concepto con esa clave en el catálogo.',
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
