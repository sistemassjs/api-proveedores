<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Normaliza correo / teléfono de usuario (login por email o teléfono, legacy SP).
 */
class UserContactData
{
    public static function isEmail(?string $value): bool
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isPhoneDigits(?string $value): bool
    {
        $digits = preg_replace('/\D/', '', (string) ($value ?? ''));

        return $digits !== '' && (bool) preg_match('/^[0-9]{6,15}$/', $digits);
    }

    public static function digitsOnly(?string $value): string
    {
        return preg_replace('/\D/', '', (string) ($value ?? '')) ?? '';
    }

    /**
     * @return array{email: string, telefono: string|null, telefono_codigo_pais: string|null}
     */
    public static function normalizeForStorage(
        ?string $email,
        ?string $telefono,
        ?string $codigoPais
    ): array {
        $email = trim((string) ($email ?? ''));
        $telefono = self::digitsOnly($telefono);
        $codigo = trim((string) ($codigoPais ?? ''));
        if ($codigo === '') {
            $codigo = '+52';
        }

        $hasEmail = self::isEmail($email);
        $hasPhone = self::isPhoneDigits($telefono);

        if (! $hasEmail && ! $hasPhone) {
            throw ValidationException::withMessages([
                'email' => ['Indica un correo electrónico o un teléfono válido.'],
                'telefono' => ['Indica un correo electrónico o un teléfono válido.'],
            ]);
        }

        if ($hasEmail && $hasPhone) {
            return [
                'email' => $email,
                'telefono' => $telefono,
                'telefono_codigo_pais' => $codigo,
            ];
        }

        if ($hasEmail) {
            return [
                'email' => $email,
                'telefono' => null,
                'telefono_codigo_pais' => null,
            ];
        }

        return [
            'email' => $telefono,
            'telefono' => $telefono,
            'telefono_codigo_pais' => $codigo,
        ];
    }
}
