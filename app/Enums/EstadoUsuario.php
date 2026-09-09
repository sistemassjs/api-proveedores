<?php

namespace App\Enums;

enum EstadoUsuario: string
{
    case REGISTRADO = 'registrado';
    case VERIFICADO = 'verificado';
    case SUSPENDIDO = 'suspendido';
    case BLOQUEADO = 'bloqueado';
    case REGISTRO_COMPLETADO = 'registro_completado';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::REGISTRADO => 'Registrado',
            self::VERIFICADO => 'Verificado',
            self::SUSPENDIDO => 'Suspendido',
            self::BLOQUEADO => 'Bloqueado',
            self::REGISTRO_COMPLETADO => 'Registro completado',
        };
    }
}
