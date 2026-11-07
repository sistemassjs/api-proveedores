<?php

namespace App\Http\Requests\Presupuesto;

use App\Models\PresupuestoCuadrilla;
use App\Services\Presupuesto\PresupuestoMatrizCalculoService;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePresupuestoCuadrillaRequest extends FormRequest
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
            'clave' => ['sometimes', 'nullable', 'string', 'max:'.PresupuestoCuadrilla::CLAVE_MAX],
            'nombre' => ['sometimes', 'required', 'string', 'max:'.PresupuestoCuadrilla::NOMBRE_MAX],
            'integrantes' => ['sometimes', 'nullable', 'string', 'max:'.PresupuestoCuadrilla::INTEGRANTES_MAX],
            'unidad' => ['sometimes', 'nullable', 'string', 'max:'.PresupuestoCuadrilla::UNIDAD_MAX],
            'costo_unitario' => ['sometimes', 'required', 'numeric', 'min:0'],
            'orden' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->exists('clave')) {
            $merge['clave'] = app(PresupuestoMatrizCalculoService::class)
                ->normalizarClave($this->input('clave'));
        }
        if ($this->exists('nombre')) {
            $merge['nombre'] = trim((string) $this->input('nombre', ''));
        }
        if ($this->exists('integrantes')) {
            $t = trim((string) $this->input('integrantes', ''));
            $merge['integrantes'] = $t === '' ? null : $t;
        }
        if ($this->exists('unidad')) {
            $u = trim((string) $this->input('unidad', 'jor'));
            $merge['unidad'] = $u === '' ? 'jor' : $u;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
