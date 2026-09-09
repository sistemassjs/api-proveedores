<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\NormalizesUserContactInput;
use App\Support\UserContactData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * @OA\Schema(
 *     schema="UserUpdateRequest",
 *     required={"name"},
 *
 *     @OA\Property(property="name", type="string", example="Juan Pérez", maxLength=255),
 *     @OA\Property(property="email", type="string", example="juan.perez@example.com", maxLength=255),
 *     @OA\Property(property="telefono", type="string", example="6681234567"),
 *     @OA\Property(property="telefono_codigo_pais", type="string", example="+52"),
 *     @OA\Property(property="password", type="string", format="password", example="contraseña123", maxLength=255),
 *     @OA\Property(property="role_id", type="integer", example=2)
 * )
 */
class UserUpdateRequest extends FormRequest
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

        if ($this->filled('estado') && ! $this->filled('status')) {
            $this->merge(['status' => $this->input('estado')]);
        }

        $this->prepareUserContactForValidation();
    }

    public function rules(): array
    {
        $userId = (int) ($this->route('usuario') ?? $this->route('user') ?? $this->route('id'));

        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['nullable', 'string', Password::min(8), 'confirmed'],
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'role' => ['sometimes', 'integer', 'exists:roles,id'],
            'status' => ['sometimes', 'string'],
            'estado' => ['sometimes'],
            'es_cuenta_de_pruebas' => ['sometimes', 'boolean'],
        ], $this->userContactRules($userId > 0 ? $userId : null));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(['email', 'telefono', 'telefono_codigo_pais'])) {
                return;
            }

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

        if ($this->hasAny(['email', 'telefono', 'telefono_codigo_pais'])) {
            $validated = $this->mergeNormalizedUserContact($validated);
        }

        return $validated;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.unique' => 'Este correo o usuario ya está registrado.',
            'telefono.regex' => 'El teléfono debe tener entre 6 y 15 dígitos.',
            'telefono.unique' => 'Este teléfono ya está registrado.',
            'telefono_codigo_pais.regex' => 'El código de país debe tener formato internacional, por ejemplo +52.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'role_id.exists' => 'El rol seleccionado no es válido.',
        ];
    }
}
