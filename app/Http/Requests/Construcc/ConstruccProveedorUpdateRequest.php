<?php

namespace App\Http\Requests\Construcc;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ConstruccProveedorUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    /**
     * Misma respuesta JSON que {@see ConstruccProveedorStoreRequest} ante errores de validación.
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
        $proveedorId = $this->route('proveedor')->id ?? null;

        return [
            // Datos del proveedor con validación sometimes
            'razon_social' => 'sometimes|string|max:255|unique:proveedores,razon_social,' . $proveedorId,
            'rfc' => 'sometimes|string|min:12|max:13|unique:proveedores,rfc,' . $proveedorId,
            'nombre_comercial' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:proveedores,email,' . $proveedorId,
            'telefono' => 'sometimes|string|max:20|unique:proveedores,telefono,' . $proveedorId,
            'celular' => 'sometimes|nullable|string|max:20',

            // Datos de autorización (requeridos para validar permisos)
            'usuario_id' => 'required|integer',
            'nivel_id' => 'required|integer|min:0|max:7', // 0: Admin, 1: DG, 2: DT, 3: DA, 4: SI, 5: PC, 6: RO, 7

            // Cuentas bancarias (opcional, array de cuentas)
            // 'cuentas_bancarias' => 'sometimes|array',
            // 'cuentas_bancarias.*.id' => 'sometimes|integer|exists:cuentas_bancarias,id',
            // 'cuentas_bancarias.*.alias' => 'sometimes|string|max:255',
            // 'cuentas_bancarias.*.banco_clave' => 'sometimes|string|max:10',
            // 'cuentas_bancarias.*.banco_nombre' => 'sometimes|string|max:255',
            // 'cuentas_bancarias.*.tipo_cuenta' => 'sometimes|string|max:255',
            // 'cuentas_bancarias.*.campo_dependiente' => 'sometimes|string|max:255',
            // 'cuentas_bancarias.*.titular_cuenta' => 'sometimes|string|max:255',
            // 'cuentas_bancarias.*.referencia' => 'sometimes|nullable|string|max:255',
            // 'cuentas_bancarias.*.sucursal' => 'sometimes|nullable|string|max:255',
            // 'cuentas_bancarias.*.swift' => 'sometimes|nullable|string|max:255',
            // 'cuentas_bancarias.*.preferida' => 'sometimes|boolean',
            'cuentas_bancarias.id' => 'sometimes|integer|exists:cuentas_bancarias,id',
            'cuentas_bancarias.alias' => 'sometimes|string|max:255',
            'cuentas_bancarias.banco_clave' => 'sometimes|string|max:10',
            'cuentas_bancarias.banco_nombre' => 'sometimes|string|max:255',
            'cuentas_bancarias.tipo_cuenta' => 'sometimes|string|max:255',
            // 'cuentas_bancarias.campo_dependiente' => 'sometimes|string|max:255',
            'cuentas_bancarias.campo_dependiente' => [
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
            'cuentas_bancarias.titular_cuenta' => 'sometimes|string|max:255',
            'cuentas_bancarias.referencia' => 'sometimes|nullable|string|max:255',
            'cuentas_bancarias.sucursal' => 'sometimes|nullable|string|max:255',
            'cuentas_bancarias.swift' => 'sometimes|nullable|string|max:255',
            'cuentas_bancarias.preferida' => 'sometimes|boolean',

        ];
    }

    public function messages()
    {
        return [
            // Mensajes para datos del proveedor
            'razon_social.string' => 'La razón social debe ser texto válido',
            'razon_social.max' => 'La razón social no debe exceder los 255 caracteres',
            'razon_social.unique' => 'La razón social ya está registrada en el sistema',

            'rfc.string' => 'El RFC debe ser texto válido',
            'rfc.min' => 'El RFC debe tener al menos 12 caracteres',
            'rfc.max' => 'El RFC no debe exceder los 13 caracteres',
            'rfc.unique' => 'El RFC ya está registrado en el sistema',

            'nombre_comercial.string' => 'El nombre comercial debe ser texto válido',
            'nombre_comercial.max' => 'El nombre comercial no debe exceder los 255 caracteres',

            'email.email' => 'El email debe ser una dirección válida',
            'email.max' => 'El email no debe exceder los 255 caracteres',
            'email.unique' => 'El email ya está registrado en el sistema',

            'telefono.string' => 'El teléfono debe ser texto válido',
            'telefono.max' => 'El teléfono no debe exceder los 20 caracteres',
            'telefono.unique' => 'El teléfono ya está registrado en el sistema',

            'celular.string' => 'El celular debe ser texto válido',
            'celular.max' => 'El celular no debe exceder los 20 caracteres',

            // Mensajes para autorización
            'usuario_id.required' => 'El ID del usuario es obligatorio para validar permisos',
            'usuario_id.integer' => 'El ID del usuario debe ser un número entero',

            'nivel_id.required' => 'El nivel del usuario es obligatorio para validar permisos',
            'nivel_id.integer' => 'El nivel del usuario debe ser un número entero',
            'nivel_id.min' => 'El nivel del usuario debe ser mayor o igual a 0',
            'nivel_id.max' => 'El nivel del usuario no debe exceder 7',

            // Mensajes para cuentas bancarias
            'cuentas_bancarias.array' => 'Las cuentas bancarias deben ser un arreglo',
            'cuentas_bancarias.*.id.integer' => 'El ID de la cuenta debe ser un número entero',
            'cuentas_bancarias.*.id.exists' => 'La cuenta bancaria no existe',
            'cuentas_bancarias.*.alias.string' => 'El alias debe ser texto válido',
            'cuentas_bancarias.*.alias.max' => 'El alias no debe exceder los 255 caracteres',
            'cuentas_bancarias.*.banco_clave.string' => 'La clave del banco debe ser texto válido',
            'cuentas_bancarias.*.banco_clave.max' => 'La clave del banco no debe exceder los 10 caracteres',
            'cuentas_bancarias.*.banco_nombre.string' => 'El nombre del banco debe ser texto válido',
            'cuentas_bancarias.*.banco_nombre.max' => 'El nombre del banco no debe exceder los 255 caracteres',
            'cuentas_bancarias.*.tipo_cuenta.string' => 'El tipo de cuenta debe ser texto válido',
            'cuentas_bancarias.*.tipo_cuenta.max' => 'El tipo de cuenta no debe exceder los 255 caracteres',
            'cuentas_bancarias.*.campo_dependiente.string' => 'El campo dependiente debe ser texto válido',
            'cuentas_bancarias.*.campo_dependiente.max' => 'El campo dependiente no debe exceder los 255 caracteres',
            'cuentas_bancarias.*.titular_cuenta.string' => 'El titular de la cuenta debe ser texto válido',
            'cuentas_bancarias.*.titular_cuenta.max' => 'El titular de la cuenta no debe exceder los 255 caracteres',
            'cuentas_bancarias.*.referencia.max' => 'La referencia no debe exceder los 255 caracteres',
            'cuentas_bancarias.*.sucursal.max' => 'La sucursal no debe exceder los 255 caracteres',
            'cuentas_bancarias.*.swift.max' => 'El código SWIFT no debe exceder los 255 caracteres',
            'cuentas_bancarias.*.preferida.boolean' => 'El campo preferida debe ser verdadero o falso',
        ];
    }
}
