<?php

namespace App\Enums;

enum EstadoSolicitudCotizacion: string
{
    case RECIBIDA = 'recibida';
    case EN_REVISION = 'en_revision';
    case RESPONDIDA = 'respondida';
    case PROCESADA = 'procesada';
    case CERRADA = 'cerrada';
    case RECHAZADA = 'rechazada';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::RECIBIDA => 'Recibida',
            self::EN_REVISION => 'En revisión',
            self::RESPONDIDA => 'Respondida',
            self::PROCESADA => 'Procesada',
            self::CERRADA => 'Cerrada',
            self::RECHAZADA => 'Rechazada',
        };
    }
}
