<?php

namespace App\Http\Requests\Auth;

use App\Rules\ReCaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="ProveedorRegisterRequest",
 *     required={
 *         "nombre_comercial",
 *         "razon_social",
 *         "tipos_empresa_id",
 *         "email",
 *         "telefono",
 *         "contacto_nombre",
 *         "contacto_telefono",
 *         "contacto_correo",
 *         "recaptcha_token"
 *     },
 *
 *     @OA\Property(property="nombre_comercial", type="string", maxLength=255),
 *     @OA\Property(property="razon_social", type="string", maxLength=255),
 *     @OA\Property(property="tipos_empresa_id", type="integer", example=1),
 *     @OA\Property(property="tipos_empresa_otro", type="string", maxLength=60, nullable=true),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255),
 *     @OA\Property(
 *         property="telefono",
 *         type="object",
 *         @OA\Property(property="codigo", type="string", example="+52"),
 *         @OA\Property(property="telefono", type="string", example="6688112233")
 *     ),
 *     @OA\Property(property="contacto_nombre", type="string", maxLength=150),
 *     @OA\Property(property="contacto_telefono", type="string", maxLength=15),
 *     @OA\Property(property="contacto_correo", type="string", format="email", maxLength=60),
 *     @OA\Property(property="recaptcha_token", type="string", description="Token de validación ReCAPTCHA v3")
 * )
 */
class ProveedorRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pasamos el valor del telefono a campos planos corresponedietes al modelo del proveedor
     * correccion del nombre comercial que en realidad es el nombre del que registra el proveedor
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if ($this->has('telefono')) {
            $data['telefono'] = $this->input('telefono.telefono');
            $data['telefono_codigo_pais'] = $this->input('telefono.codigo');
        }
        
        $data['nombre_propietario'] = $this->input('nombre_comercial');
        $data['nombre_comercial'] = $this->input('razon_social');
        $data['razon_social'] = $this->input('razon_social');

        return $data;
    }


    public function rules(): array
    {
        return [
            'nombre_comercial' => ['required', 'string', 'max:255'], // 
            'razon_social' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email'),
                Rule::unique('proveedores', 'email'),
            ],

            'telefono' => ['nullable', 'array'],
            'telefono.codigo' => ['nullable', 'string', 'regex:/^\+[0-9]{1,4}$/'],
            'telefono.telefono' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/'],

            // No utilizados
            // 'tipos_empresa_id' => ['nullable', 'integer', 'exists:tipos_empresa,id,estatus,activo'],
            'tipos_empresa_id' => ['nullable', 'integer'],
            'tipos_empresa_otro' => ['nullable', 'max:60'],
            'contacto_nombre' => ['nullable', 'string', 'max:150'],
            'contacto_telefono' => ['nullable', 'string', 'max:15'],
            'contacto_correo' => ['nullable', 'email', 'max:60'],
            'acepta_terminos' => ['required', 'accepted'],
            // 'recaptcha_token' => ['required', new ReCaptcha],

        ];
    }

    public function messages()
    {
        return [

            'nombre_comercial.required' => 'El nombre comercial es obligatorio.',
            'nombre_comercial.string' => 'El nombre comercial debe ser una cadena de texto.',
            'nombre_comercial.max' => 'El nombre comercial no debe exceder los 255 caracteres.',

            'razon_social.required' => 'La razón social es obligatoria.',
            'razon_social.string' => 'La razón social debe ser una cadena de texto.',
            'razon_social.max' => 'La razón social no debe exceder los 255 caracteres.',

            'tipos_empresa_id.required' => 'El tipo de empresa es obligatorio.',
            'tipos_empresa_id.integer' => 'El tipo de empresa debe ser un número entero.',
            'tipos_empresa_id.exists' => 'El tipo de empresa seleccionado no es válido o está inactivo.',

            'tipos_empresa_otro.string' => 'El campo "otro" del tipo de empresa debe ser una cadena de texto.',
            'tipos_empresa_otro.max' => 'El campo "otro" del tipo de empresa no debe exceder los 60 caracteres.',

            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe tener un formato válido.',
            'email.max' => 'El correo electrónico no debe exceder los 255 caracteres.',
            'email.unique' => 'El correo electrónico ya está registrado.',

            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.array' => 'El teléfono debe tener formato válido.',
            'telefono.codigo.required' => 'El código de país es obligatorio.',
            'telefono.codigo.string' => 'El código de país debe ser una cadena de texto.',
            'telefono.codigo.regex' => 'El código de país debe tener formato internacional, por ejemplo +52.',
            'telefono.telefono.required' => 'El número de teléfono es obligatorio.',
            'telefono.telefono.string' => 'El número de teléfono debe ser una cadena de texto.',
            'telefono.telefono.regex' => 'El número de teléfono solo debe contener dígitos y tener entre 6 y 15 caracteres.',

            'contacto_nombre.required' => 'El nombre del contacto es obligatorio.',
            'contacto_nombre.string' => 'El nombre del contacto debe ser una cadena de texto.',
            'contacto_nombre.max' => 'El nombre del contacto no debe exceder los 150 caracteres.',

            'contacto_telefono.required' => 'El teléfono del contacto es obligatorio.',
            'contacto_telefono.string' => 'El teléfono del contacto debe ser una cadena de texto.',
            'contacto_telefono.max' => 'El teléfono del contacto no debe exceder los 15 caracteres.',

            'contacto_correo.required' => 'El correo electrónico del contacto es obligatorio.',
            'contacto_correo.email' => 'El correo electrónico del contacto debe tener un formato válido.',
            'contacto_correo.max' => 'El correo electrónico del contacto no debe exceder los 60 caracteres.',

            'acepta_terminos.required' => 'Debe aceptar los términos y condiciones para registrarse.',
            'acepta_terminos.accepted' => 'Debe aceptar los términos y condiciones para registrarse.',
        ];
    }
}
