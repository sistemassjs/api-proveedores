<?php

namespace App\Http\Requests\ConfigEmisorReceptorPresupuesto;

use App\Http\Requests\ConfigEmisorReceptorPresupuesto\Concerns\MapsConfigEmisorReceptorPresupuestoInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreConfigEmisorReceptorPresupuestoRequest extends FormRequest
{
    use MapsConfigEmisorReceptorPresupuestoInput;

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
            'tipo' => ['required', 'string', 'in:receptor,emisor'],
            'subfijo' => ['nullable', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:60'],
            'ape1' => ['nullable', 'string', 'max:60'],
            'ape2' => ['nullable', 'string', 'max:60'],
            'puesto' => ['nullable', 'string', 'max:40'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:120'],
            'color_fondo' => ['nullable', 'string', 'max:7'],
            'foto_perfil' => ['nullable', 'file', 'image', 'max:5120'],
            'file_firma' => ['nullable', 'file', 'image', 'max:5120'],
            'estado' => ['required', 'string', 'in:activo,inactivo,default'],
            'incluir_leyenda_atentamente' => ['sometimes', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $data['tipo'] = $this->mapTipoToInt($data['tipo'] ?? null);
        $data['estado'] = $this->mapEstadoToInt($data['estado'] ?? null);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser emisor o receptor.',
            'nombre.required' => 'El nombre es obligatorio.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser activo, inactivo o default.',
        ];
    }
}
