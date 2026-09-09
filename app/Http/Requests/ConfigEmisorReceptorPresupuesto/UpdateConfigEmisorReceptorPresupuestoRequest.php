<?php

namespace App\Http\Requests\ConfigEmisorReceptorPresupuesto;

use App\Http\Requests\ConfigEmisorReceptorPresupuesto\Concerns\MapsConfigEmisorReceptorPresupuestoInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigEmisorReceptorPresupuestoRequest extends FormRequest
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
            'tipo' => ['sometimes', 'string', 'in:receptor,emisor'],
            'subfijo' => ['sometimes', 'nullable', 'string', 'max:30'],
            'nombre' => ['sometimes', 'string', 'max:60'],
            'ape1' => ['sometimes', 'nullable', 'string', 'max:60'],
            'ape2' => ['sometimes', 'nullable', 'string', 'max:60'],
            'puesto' => ['sometimes', 'nullable', 'string', 'max:40'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:30'],
            'correo' => ['sometimes', 'nullable', 'email', 'max:120'],
            'color_fondo' => ['sometimes', 'nullable', 'string', 'max:7'],
            'foto_perfil' => ['sometimes', 'nullable', 'file', 'image', 'max:5120'],
            'file_firma' => ['sometimes', 'nullable', 'file', 'image', 'max:5120'],
            'estado' => ['sometimes', 'string', 'in:activo,inactivo,default'],
            'incluir_leyenda_atentamente' => ['sometimes', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        if (isset($data['tipo'])) {
            $data['tipo'] = $this->mapTipoToInt($data['tipo']);
        }

        if (isset($data['estado'])) {
            $data['estado'] = $this->mapEstadoToInt($data['estado']);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo debe ser emisor o receptor.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'estado.in' => 'El estado debe ser activo, inactivo o default.',
        ];
    }
}
