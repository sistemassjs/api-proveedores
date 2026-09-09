<?php

namespace App\Support;

use App\Enums\EstadoUsuario;
use App\Models\User;

final class UserCuentaEstado
{
    public static function esRestringido(mixed $status): bool
    {
        $value = self::normalize($status);

        return in_array($value, [
            EstadoUsuario::BLOQUEADO->value,
            EstadoUsuario::SUSPENDIDO->value,
        ], true);
    }

    /**
     * @return array{ok: bool, codigo?: string, message?: string}
     */
    public static function assertCanAuthenticate(User $user): array
    {
        $status = self::normalize($user->status);

        if ($status === EstadoUsuario::BLOQUEADO->value) {
            return [
                'ok' => false,
                'codigo' => 'cuenta_bloqueada',
                'message' => 'Tu cuenta está bloqueada. Contacta al administrador de GestionPlus.',
            ];
        }

        if ($status === EstadoUsuario::SUSPENDIDO->value) {
            return [
                'ok' => false,
                'codigo' => 'cuenta_suspendida',
                'message' => 'Tu cuenta está suspendida temporalmente. Contacta al administrador de GestionPlus.',
            ];
        }

        return ['ok' => true];
    }

    private static function normalize(mixed $status): string
    {
        if ($status instanceof EstadoUsuario) {
            return $status->value;
        }

        return (string) $status;
    }
}
