<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PurificadoraPedidoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'ERROR',
                'code' => 422,
                'message' => 'Error de validación',
                'data' => null,
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'celular' => ['sometimes', 'required', 'regex:/^[0-9]+$/', 'size:10'],
            'correo' => ['nullable', 'email', 'max:180'],
            'calle' => ['sometimes', 'required', 'string', 'max:120'],
            'numero' => ['sometimes', 'required', 'string', 'max:20'],
            'colonia' => ['sometimes', 'required', 'string', 'max:120'],
            'codigoPostal' => ['nullable', 'regex:/^[0-9]{5}$/'],
            'municipio' => ['nullable', 'string', 'max:120'],
            'cantidadGarrafones' => ['sometimes', 'required', 'integer', 'min:1'],
            'precioUnitario' => ['sometimes', 'required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no debe exceder 120 caracteres.',
            'celular.required' => 'El celular es obligatorio.',
            'celular.regex' => 'El celular solo debe contener números.',
            'celular.size' => 'El celular debe tener 10 dígitos.',
            'correo.email' => 'El correo no es válido.',
            'correo.max' => 'El correo no debe exceder 180 caracteres.',
            'calle.required' => 'La calle es obligatoria.',
            'calle.max' => 'La calle no debe exceder 120 caracteres.',
            'numero.required' => 'El número es obligatorio.',
            'numero.max' => 'El número no debe exceder 20 caracteres.',
            'colonia.required' => 'La colonia es obligatoria.',
            'colonia.max' => 'La colonia no debe exceder 120 caracteres.',
            'codigoPostal.regex' => 'El código postal debe tener 5 dígitos.',
            'cantidadGarrafones.required' => 'La cantidad de garrafones es obligatoria.',
            'cantidadGarrafones.integer' => 'La cantidad de garrafones debe ser un número entero.',
            'cantidadGarrafones.min' => 'La cantidad de garrafones debe ser al menos 1.',
            'precioUnitario.required' => 'El precio unitario es obligatorio.',
            'precioUnitario.numeric' => 'El precio unitario debe ser numérico.',
            'precioUnitario.min' => 'El precio unitario no puede ser negativo.',
            'notas.max' => 'Las notas no deben exceder 2000 caracteres.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosActualizacion(): array
    {
        $validated = $this->validated();
        $mapa = [
            'nombre' => 'nombre',
            'celular' => 'celular',
            'correo' => 'correo',
            'calle' => 'calle',
            'numero' => 'numero',
            'colonia' => 'colonia',
            'codigoPostal' => 'codigo_postal',
            'municipio' => 'municipio',
            'cantidadGarrafones' => 'cantidad_garrafones',
            'precioUnitario' => 'precio_unitario',
            'notas' => 'notas',
        ];

        $datos = [];
        foreach ($mapa as $input => $columna) {
            if (array_key_exists($input, $validated)) {
                $datos[$columna] = $validated[$input];
            }
        }

        return $datos;
    }
}
