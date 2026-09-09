<?php

namespace App\Http\Requests\Construcc;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConstruccProveedorStoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    /**
     * Sobrescribir el método para retornar JSON en caso de error
     */
    protected function failedValidation(Validator $validator)
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

    public function rules()
    {
        return [
            // Datos del proveedor (REQUERIDOS Y ÚNICOS)
            'razon_social' => 'required|string|max:255|unique:proveedores,razon_social',
            'rfc' => 'nullable|string|min:12|max:13|unique:proveedores,rfc',
            'email' => 'nullable|email|max:255|unique:proveedores,email',
            'telefono' => 'nullable|string|max:20|unique:proveedores,telefono',
            'celular' => 'nullable|string|max:20',

            // Datos opcionales del proveedor
            'nombre_comercial' => 'nullable|string|max:255',

            // Datos de la empresa de construcción y usuario
            'empresa_construcc_id' => 'required|exists:empresa_construcc,id',
            'usuario_id' => 'required|integer',
            'usuario_nombre' => 'required|string|max:255',
            'nivel_id' => 'nullable|integer|in:0,1,2,3,4,5,6,7', // 0=Admin, 1=DG, 2=DT, 3=DA, 4=SI, 5=PC, 6=RO, 7

            // Cuenta bancaria (OPCIONAL)
            'cuenta' => 'nullable|array|min:1',

            'cuenta.alias' => 'required_with:cuenta|string|max:255',
            'cuenta.banco_clave' => 'required_with:cuenta|string|max:10',
            'cuenta.banco_nombre' => 'required_with:cuenta|string|max:255',
            'cuenta.tipo_cuenta' => 'required_with:cuenta|string|max:255',
            'cuenta.campo_dependiente' => [
                'sometimes',
                function ($attribute, $value, $fail) {

                    $tipoCuenta = $this->input('cuentas_bancarias.tipo_cuenta');

                    // Validación para CUENTA — longitud libre; solo dígitos.
                    if ($tipoCuenta === 'cuenta') {
                        if (! ctype_digit((string) $value)) {
                            $fail('La cuenta debe contener solo números.');
                            return;
                        }
                    }

                    // Validación para CLABE
                    if ($tipoCuenta === 'clabe') {
                        if (! ctype_digit((string) $value)) {
                            $fail('La CLABE debe contener solo números.');
                            return;
                        }

                        if (strlen((string) $value) !== 18) {
                            $fail('La CLABE debe tener exactamente 18 dígitos.');
                        }
                    }

                    // Validación para TARJETA
                    if ($tipoCuenta === 'tarjeta') {
                        if (! ctype_digit((string) $value)) {
                            $fail('La tarjeta debe contener solo números.');
                            return;
                        }
                        if (strlen((string) $value) !== 16) {
                            $fail('La tarjeta debe tener exactamente 16 dígitos.');
                        }
                    }
                },
            ],
            'cuenta.titular_cuenta' => 'required_with:cuenta|string|max:255',

            'cuenta.referencia' => 'nullable|string|max:255',
            'cuenta.sucursal' => 'nullable|string|max:255',
            'cuenta.swift' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            // Mensajes para datos del proveedor
            'razon_social.required' => 'La razón social es obligatoria',
            'razon_social.unique' => 'La razón social ya está registrada en el sistema',
            'razon_social.max' => 'La razón social no debe exceder los 255 caracteres',

            'rfc.required' => 'El RFC es obligatorio',
            'rfc.unique' => 'El RFC ya está registrado en el sistema',
            'rfc.min' => 'El RFC debe tener al menos 12 caracteres',
            'rfc.max' => 'El RFC no debe exceder los 13 caracteres',

            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser una dirección válida',
            'email.unique' => 'El email ya está registrado en el sistema',
            'email.max' => 'El email no debe exceder los 255 caracteres',

            'telefono.required' => 'El teléfono es obligatorio',
            'telefono.unique' => 'El teléfono ya está registrado en el sistema',
            'telefono.max' => 'El teléfono no debe exceder los 20 caracteres',

            'celular.max' => 'El celular no debe exceder los 20 caracteres',

            'nombre_comercial.max' => 'El nombre comercial no debe exceder los 255 caracteres',

            // Mensajes para empresa construcción
            'empresa_construcc_id.required' => 'La empresa de construcción es obligatoria',
            'empresa_construcc_id.exists' => 'La empresa de construcción seleccionada no existe',

            'usuario_id.required' => 'El ID del usuario es obligatorio',
            'usuario_id.integer' => 'El ID del usuario debe ser un número entero',

            'usuario_nombre.required' => 'El nombre del usuario es obligatorio',
            'usuario_nombre.max' => 'El nombre del usuario no debe exceder 255 caracteres',

            'nivel_id.required' => 'El nivel del usuario es obligatorio',
            'nivel_id.integer' => 'El nivel del usuario debe ser un número entero',
            'nivel_id.in' => 'El nivel del usuario no es válido',

            // Mensajes para cuenta bancaria
            'cuenta.required' => 'Los datos de la cuenta bancaria son obligatorios',
            'cuenta.array' => 'Los datos de la cuenta bancaria deben ser un objeto válido',

            'cuenta.alias.required' => 'El alias de la cuenta es obligatorio',
            'cuenta.alias.max' => 'El alias no debe exceder los 255 caracteres',

            'cuenta.banco_clave.required' => 'La clave del banco es obligatoria',
            'cuenta.banco_clave.max' => 'La clave del banco no debe exceder los 10 caracteres',

            'cuenta.banco_nombre.required' => 'El nombre del banco es obligatorio',
            'cuenta.banco_nombre.max' => 'El nombre del banco no debe exceder los 255 caracteres',

            'cuenta.tipo_cuenta.required' => 'El tipo de cuenta es obligatorio',
            'cuenta.tipo_cuenta.max' => 'El tipo de cuenta no debe exceder los 255 caracteres',

            'cuenta.campo_dependiente.required' => 'El campo dependiente (CLABE/número de cuenta) es obligatorio',
            'cuenta.campo_dependiente.max' => 'El campo dependiente no debe exceder los 255 caracteres',

            'cuenta.titular_cuenta.required' => 'El titular de la cuenta es obligatorio',
            'cuenta.titular_cuenta.max' => 'El titular de la cuenta no debe exceder los 255 caracteres',

            'cuenta.referencia.max' => 'La referencia no debe exceder los 255 caracteres',
            'cuenta.sucursal.max' => 'La sucursal no debe exceder los 255 caracteres',
            'cuenta.swift.max' => 'El código SWIFT no debe exceder los 255 caracteres',
        ];
    }
}
