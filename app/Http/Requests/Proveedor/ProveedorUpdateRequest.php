<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Validation\Rule;
use App\Enums\EstadoUsuario;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="ProveedorUpdateRequest",
 *     description="Actualización parcial de empresa. En panel admin ningún campo es obligatorio."
 * )
 */
class ProveedorUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->isAdminGestionRequest()) {
            return;
        }

        $nullableKeys = [
            'nombre_comercial',
            'email',
            'telefono',
            'pagina_web',
            'direccion_empresa',
            'descripcion_giro_empresa',
            'nombre_propietario',
            'nombre_de_quien_registra',
            'razon_social',
            'rfc',
            'regimen_fiscal_clave',
            'regimen_fiscal_nombre',
            'calle',
            'numero_exterior',
            'numero_interior',
            'colonia',
            'estado',
            'ciudad',
            'codigo_postal',
            'pais',
            'contacto_nombre',
            'contacto_cargo',
            'contacto_telefono',
            'contacto_correo',
            'notas',
            'tipos_empresa_otro',
        ];

        $payload = [];
        foreach ($nullableKeys as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $payload[$key] = null;
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if ($this->has('telefono')) {
            if (is_array($this->input('telefono'))) {
                $data['telefono'] = $this->input('telefono.telefono');
                $data['telefono_codigo_pais'] = $this->input('telefono.codigo');
            } else {
                $data['telefono'] = $this->input('telefono');
            }
        }

        return $data;
    }

    public function rules(): array
    {
        $proveedor = $this->route('proveedor');
        $admin = $this->isAdminGestionRequest();

        $rules = [
            // -------- DATOS GENERALES --------
            'nombre_comercial' => [$admin ? 'nullable' : 'sometimes', 'string', 'max:255'],
            'email' => [$admin ? 'nullable' : 'sometimes', 'email', 'max:255'],
            'telefono' => ['sometimes', 'nullable'],
            'telefono.codigo' => ['sometimes', 'nullable', 'string', 'max:10'],
            'telefono.telefono' => ['sometimes', 'nullable', 'string', 'max:20'],

            'pagina_web' => ['nullable', 'string', 'max:255'],
            'direccion_empresa' => ['nullable', 'string', 'max:255'],
            'descripcion_giro_empresa' => ['nullable', 'string', 'max:255'],
            'nombre_propietario' => ['nullable', 'string', 'max:255'],
            'nombre_de_quien_registra' => ['nullable', 'string', 'max:255'],

            // -------- DATOS FISCALES --------
            'razon_social' => array_values(array_filter([
                $admin ? 'nullable' : 'sometimes',
                'string',
                $admin ? null : 'min:3',
                'max:255',
                Rule::unique('proveedores', 'razon_social')->ignore($proveedor?->id),
            ])),

            'rfc' => array_values(array_filter([
                $admin ? 'nullable' : 'sometimes',
                'string',
                $admin ? null : 'regex:/^[A-ZÑ&]{3,4}\\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\\d|3[01])[A-Z0-9]{3}$/',
                Rule::unique('proveedores', 'rfc')->ignore($proveedor?->id),
            ])),

            'regimen_fiscal_clave' => ['sometimes', 'nullable', 'string', 'max:10'],
            'regimen_fiscal_nombre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'regimenes_fiscales' => ['sometimes', 'nullable', 'array'],
            'regimenes_fiscales.*.id' => ['nullable', 'integer'],
            'regimenes_fiscales.*.clave' => ['required_with:regimenes_fiscales', 'string', 'max:10'],
            'regimenes_fiscales.*.nombre' => ['required_with:regimenes_fiscales', 'string', 'max:255'],
            'regimenes_fiscales.*.fecha_alta' => ['nullable', 'date'],
            'regimenes_fiscales.*.fecha_fin' => ['nullable', 'date'],
            'regimenes_fiscales.*.es_principal' => ['sometimes', 'boolean'],
            'regimenes_fiscales.*.origen' => ['nullable', 'string', 'max:30'],
            'calle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'numero_exterior' => ['nullable', 'string', 'max:20'],
            'numero_interior' => ['nullable', 'string', 'max:20'],
            'colonia' => ['sometimes', 'nullable', 'string', 'max:255'],
            'estado' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ciudad' => ['sometimes', 'nullable', 'string', 'max:255'],
            'codigo_postal' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'pais' => ['sometimes', 'nullable', 'string', 'max:255'],

            // -------- CONTACTO --------
            'contacto_nombre' => ['nullable', 'string', 'max:150'],
            'contacto_cargo' => ['nullable', 'string', 'max:60'],
            'contacto_telefono' => ['nullable', 'string', 'max:15'],
            'contacto_correo' => ['nullable', 'email', 'max:60'],

            // -------- ESTADO (gestión administrativa) --------
            'estatus' => ['sometimes', Rule::in(EstadoUsuario::values())],
            'notas' => ['nullable', 'string'],
            'is_proveedor_sp' => ['sometimes', 'boolean'],
            'is_proveedor_catalogo' => ['sometimes', 'boolean'],
            'tipos_empresa_id' => ['sometimes', 'nullable', 'integer', 'exists:tipos_empresa,id'],
            'tipos_empresa_otro' => ['nullable', 'string', 'max:255'],
        ];

        if ($admin) {
            $rules['es_cuenta_de_pruebas'] = ['sometimes', 'boolean'];
            // En admin RFC/código postal: validar formato solo si viene valor
            $rules['rfc'] = [
                'nullable',
                'string',
                'max:13',
                Rule::unique('proveedores', 'rfc')->ignore($proveedor?->id),
            ];
            $rules['codigo_postal'] = ['nullable', 'string', 'max:10'];
            $rules['razon_social'] = [
                'nullable',
                'string',
                'max:255',
                Rule::unique('proveedores', 'razon_social')->ignore($proveedor?->id),
            ];
            $rules['nombre_comercial'] = ['nullable', 'string', 'max:255'];
            $rules['email'] = ['nullable', 'email', 'max:255'];
        }

        return $rules;
    }

    private function isAdminGestionRequest(): bool
    {
        return $this->routeIs('admin.*')
            || str_contains($this->path(), 'admin/');
    }

    public function messages()
    {
        return [
            // -------- LOGO --------
            'logo.image' => 'El archivo debe ser una imagen válida.',
            'logo.mimes' => 'La imagen debe estar en formato JPG o PNG.',
            'logo.max' => 'La imagen no debe pesar más de 2MB.',

            // -------- GENERALES --------
            'nombre_propietario.string' => 'El nombre del propietario debe ser una cadena de texto.',
            'nombre_propietario.max' => 'El nombre del propietario no debe exceder los 255 caracteres.',

            'nombre_de_quien_registra.string' => 'El nombre de quien registra debe ser una cadena de texto.',
            'nombre_de_quien_registra.max' => 'El nombre de quien registra no debe exceder los 255 caracteres.',

            'nombre_comercial.sometimes' => 'El nombre comercial es obligatorio.',
            'nombre_comercial.string' => 'El nombre comercial debe ser una cadena de texto.',
            'nombre_comercial.max' => 'El nombre comercial no debe exceder los 255 caracteres.',

            'pagina_web.string' => 'La página web debe ser una cadena de texto.',
            'pagina_web.max' => 'La página web no debe exceder los 255 caracteres.',

            'telefono.sometimes' => 'El teléfono es obligatorio.',
            'telefono.string' => 'El teléfono debe ser una cadena de texto.',
            'telefono.max' => 'El teléfono no debe exceder los 15 caracteres.',

            'email.sometimes' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe tener un formato válido.',
            'email.max' => 'El correo electrónico no debe exceder los 255 caracteres.',
            'email.unique' => 'El correo electrónico ya está registrado.',

            'direccion_empresa.string' => 'La dirección de la empresa debe ser una cadena de texto.',
            'direccion_empresa.max' => 'La dirección de la empresa no debe exceder los 255 caracteres.',

            'descripcion_giro_empresa.string' => 'La descripción del giro de la empresa debe ser una cadena de texto.',
            'descripcion_giro_empresa.max' => 'La descripción del giro de la empresa no debe exceder los 255 caracteres.',

            // -------- DATOS FISCALES --------
            'razon_social.sometimes' => 'La razón social es obligatoria.',
            'razon_social.string' => 'La razón social debe ser una cadena de texto.',
            'razon_social.min' => 'La razón social debe contener al menos 3 caracteres.',
            'razon_social.max' => 'La razón social no debe exceder los 255 caracteres.',
            'razon_social.unique' => 'La razón social ingresada ya está registrada.',

            'rfc.sometimes' => 'El RFC es obligatorio.',
            'rfc.string' => 'El RFC debe ser una cadena de texto.',
            'rfc.regex' => 'El RFC no tiene un formato válido según el SAT.',
            'rfc.unique' => 'El RFC ingresado ya está registrado.',

            'regimen_fiscal_clave.string' => 'La clave del régimen fiscal debe ser una cadena de texto.',
            'regimen_fiscal_clave.max' => 'La clave del régimen fiscal no debe exceder los 10 caracteres.',

            'regimen_fiscal_nombre.string' => 'El nombre del régimen fiscal debe ser una cadena de texto.',
            'regimen_fiscal_nombre.max' => 'El nombre del régimen fiscal no debe exceder los 255 caracteres.',

            'calle.string' => 'La calle debe ser una cadena de texto.',
            'calle.max' => 'La calle no debe exceder los 255 caracteres.',

            'numero_exterior.string' => 'El número exterior debe ser una cadena de texto.',
            'numero_exterior.max' => 'El número exterior no debe exceder los 20 caracteres.',

            'numero_interior.string' => 'El número interior debe ser una cadena de texto.',
            'numero_interior.max' => 'El número interior no debe exceder los 20 caracteres.',

            'colonia.string' => 'La colonia debe ser una cadena de texto.',
            'colonia.max' => 'La colonia no debe exceder los 255 caracteres.',

            'estado.string' => 'El estado debe ser una cadena de texto.',
            'estado.max' => 'El estado no debe exceder los 255 caracteres.',

            'ciudad.string' => 'La ciudad debe ser una cadena de texto.',
            'ciudad.max' => 'La ciudad no debe exceder los 255 caracteres.',

            'codigo_postal.string' => 'El código postal debe ser una cadena de texto.',
            'codigo_postal.regex' => 'El código postal debe contener exactamente 5 dígitos.',

            'pais.string' => 'El país debe ser una cadena de texto.',
            'pais.max' => 'El país no debe exceder los 255 caracteres.',

            // -------- CONTACTO --------
            'contacto_nombre.string' => 'El nombre del contacto debe ser una cadena de texto.',
            'contacto_nombre.max' => 'El nombre del contacto no debe exceder los 150 caracteres.',

            'contacto_cargo.string' => 'El cargo del contacto debe ser una cadena de texto.',
            'contacto_cargo.max' => 'El cargo del contacto no debe exceder los 60 caracteres.',

            'contacto_telefono.string' => 'El teléfono del contacto debe ser una cadena de texto.',
            'contacto_telefono.max' => 'El teléfono del contacto no debe exceder los 15 caracteres.',

            'contacto_correo.email' => 'El correo electrónico de contacto debe tener un formato válido.',
            'contacto_correo.max' => 'El correo electrónico de contacto no debe exceder los 60 caracteres.',

            // -------- OTROS --------
            'estatus.enum' => 'El estatus debe ser uno de los valores permitidos: ' . implode(', ', EstadoUsuario::values()),
        ];
    }
}
