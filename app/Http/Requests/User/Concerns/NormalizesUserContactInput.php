<?php

namespace App\Http\Requests\User\Concerns;

use App\Support\UserContactData;
use Illuminate\Validation\Rule;

trait NormalizesUserContactInput
{
    protected function prepareUserContactForValidation(): void
    {
        $telefono = $this->input('telefono');

        if (is_array($telefono)) {
            $this->merge([
                'telefono' => UserContactData::digitsOnly($telefono['telefono'] ?? null),
                'telefono_codigo_pais' => $telefono['codigo'] ?? $this->input('telefono_codigo_pais'),
            ]);
        } elseif (is_string($telefono)) {
            $this->merge([
                'telefono' => UserContactData::digitsOnly($telefono),
            ]);
        }

        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => trim($this->input('email'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function userContactRules(?int $userId = null): array
    {
        $emailUnique = Rule::unique('users', 'email');
        $telefonoUnique = Rule::unique('users', 'telefono');

        if ($userId !== null) {
            $emailUnique = $emailUnique->ignore($userId);
            $telefonoUnique = $telefonoUnique->ignore($userId);
        }

        return [
            'email' => ['nullable', 'string', 'max:255', $emailUnique],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9]{6,15}$/', $telefonoUnique],
            'telefono_codigo_pais' => ['nullable', 'string', 'max:10', 'regex:/^\+[0-9]{1,4}$/'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function mergeNormalizedUserContact(array $validated): array
    {
        $contact = UserContactData::normalizeForStorage(
            $validated['email'] ?? null,
            $validated['telefono'] ?? null,
            $validated['telefono_codigo_pais'] ?? null,
        );

        $validated['email'] = $contact['email'];
        $validated['telefono'] = $contact['telefono'];
        $validated['telefono_codigo_pais'] = $contact['telefono_codigo_pais'];

        return $validated;
    }
}
