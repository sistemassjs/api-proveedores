<?php

namespace App\Http\Requests\User;

use App\Enums\EstadoUsuario;
use App\Http\Requests\User\Concerns\NormalizesUserContactInput;
use App\Support\UserContactData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * @OA\Schema(
 *     schema="UserStoreRequest",
 *     required={"name","password","role_id"},
 *     @OA\Property(property="name", type="string", maxLength=255, example="Juan Pérez"),
 *     @OA\Property(property="email", type="string", maxLength=255, example="juan.perez@example.com"),
 *     @OA\Property(property="telefono", type="string", example="6681234567"),
 *     @OA\Property(property="telefono_codigo_pais", type="string", example="+52"),
 *     @OA\Property(property="password", type="string", format="password", example="contraseña123"),
 *     @OA\Property(property="password_confirmation", type="string", format="password"),
 *     @OA\Property(property="role_id", type="integer", example=2),
 *     @OA\Property(property="status", type="string", example="registrado")
 * )
 */
class UserStoreRequest extends FormRequest
{
    use NormalizesUserContactInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('role') && ! $this->filled('role_id')) {
            $this->merge(['role_id' => $this->input('role')]);
        }

        $this->prepareUserContactForValidation();
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'status' => ['sometimes', 'string', Rule::in(EstadoUsuario::values())],
        ], $this->userContactRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $email = trim((string) $this->input('email', ''));
            $phone = UserContactData::digitsOnly($this->input('telefono'));

            if ($email !== '' && ! UserContactData::isEmail($email) && ! UserContactData::isPhoneDigits($phone)) {
                $validator->errors()->add('email', 'El correo debe tener un formato válido o indica un teléfono.');
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null) {
            return $validated;
        }

        return $this->mergeNormalizedUserContact($validated);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.unique' => 'Este correo o usuario ya está registrado.',
            'telefono.regex' => 'El teléfono debe tener entre 6 y 15 dígitos.',
            'telefono.unique' => 'Este teléfono ya está registrado.',
            'telefono_codigo_pais.regex' => 'El código de país debe tener formato internacional, por ejemplo +52.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'role_id.required' => 'El rol es obligatorio.',
            'role_id.exists' => 'El rol seleccionado no es válido.',
            'status.in' => 'El estado del usuario no es válido.',
        ];
    }
}
