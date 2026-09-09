<?php

namespace App\Http\Requests\Construcc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudPagoAutorizarParcialRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Todos los roles DG, DT, PC, SI pueden autorizar parcialmente
        return true;
    }

    public function rules(): array
    {
        return [
            'rol' => ['required', 'string', Rule::in(['DG', 'DT', 'PC', 'SI'])],
            'monto_autorizado' => ['required', 'numeric', 'gt:0'],
            'notas_autorizacion' => ['required', 'string', 'min:1'],
            'usuario_id' => ['required', 'integer'],
            'usuario_nombre' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'rol.required' => 'Debe indicar el rol que autoriza la solicitud.',
            'rol.in' => 'El rol debe ser uno de los siguientes: DG, DT, PC o SI.',
            'monto_autorizado.required' => 'El monto autorizado es obligatorio.',
            'monto_autorizado.numeric' => 'El monto autorizado debe ser un número.',
            'monto_autorizado.gt' => 'El monto autorizado debe ser mayor a 0.',
            'notas_autorizacion.required' => 'Las notas de autorización son obligatorias.',
            'notas_autorizacion.min' => 'Las notas de autorización deben tener al menos 10 caracteres.',
            'usuario_id.required' => 'El ID del usuario es obligatorio.',
            'usuario_id.integer' => 'El ID del usuario debe ser un número entero.',
            'usuario_nombre.required' => 'El nombre del usuario es obligatorio.',
            'usuario_nombre.max' => 'El nombre del usuario no debe exceder los 255 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'rol' => 'rol que autoriza',
            'monto_autorizado' => 'monto autorizado',
            'notas_autorizacion' => 'notas de autorización',
            'usuario_id' => 'ID del usuario',
            'usuario_nombre' => 'nombre del usuario',
        ];
    }
}
