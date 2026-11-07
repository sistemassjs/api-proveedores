<?php

namespace App\Http\Requests\Presupuesto;

use App\Models\PresupuestoCuadrilla;
use App\Services\Presupuesto\PresupuestoMatrizCalculoService;
use Illuminate\Foundation\Http\FormRequest;

class StorePresupuestoCuadrillaRequest extends FormRequest
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
            'clave' => ['nullable', 'string', 'max:'.PresupuestoCuadrilla::CLAVE_MAX],
            'nombre' => ['required', 'string', 'max:'.PresupuestoCuadrilla::NOMBRE_MAX],
            'integrantes' => ['nullable', 'string', 'max:'.PresupuestoCuadrilla::INTEGRANTES_MAX],
            'unidad' => ['nullable', 'string', 'max:'.PresupuestoCuadrilla::UNIDAD_MAX],
            'costo_unitario' => ['required', 'numeric', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $clave = app(PresupuestoMatrizCalculoService::class)
            ->normalizarClave($this->input('clave'));

        $this->merge([
            'clave' => $clave,
            'nombre' => trim((string) $this->input('nombre', '')),
            'integrantes' => ($t = trim((string) $this->input('integrantes', ''))) === '' ? null : $t,
            'unidad' => ($u = trim((string) $this->input('unidad', 'jor'))) === '' ? 'jor' : $u,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la cuadrilla es obligatorio.',
            'costo_unitario.required' => 'El costo de la cuadrilla es obligatorio.',
            'costo_unitario.min' => 'El costo de la cuadrilla no puede ser negativo.',
        ];
    }
}
