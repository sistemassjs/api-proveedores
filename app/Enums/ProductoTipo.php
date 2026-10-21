<?php

namespace App\Enums;

/**
 * Bandera comercial del ítem de catálogo (`productos.tipo`).
 * Agregar un caso aquí publica la bandera en validación, mensajes y catálogo del listado.
 */
enum ProductoTipo: string
{
    case Producto = 'p';
    case Servicio = 's';
    case Renta = 'r';

    public function label(): string
    {
        return match ($this) {
            self::Producto => 'Producto',
            self::Servicio => 'Servicio',
            self::Renta => 'Renta',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Catálogo para armar el selector en NexProv.
     *
     * @return list<array{codigo: string, nombre: string}>
     */
    public static function catalogo(): array
    {
        return array_map(
            fn (self $caso) => [
                'codigo' => $caso->value,
                'nombre' => $caso->label(),
            ],
            self::cases()
        );
    }

    public static function mensajeValores(): string
    {
        return implode(', ', array_map(
            fn (self $caso) => $caso->value.' ('.$caso->label().')',
            self::cases()
        ));
    }

    /**
     * Acepta la bandera corta o el nombre legado (producto, servicio, renta).
     */
    public static function tryNormalize(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return null;
        }

        return match ($raw) {
            'p', 'producto' => self::Producto,
            's', 'servicio' => self::Servicio,
            'r', 'renta' => self::Renta,
            default => null,
        };
    }

    /**
     * Código canónico para persistir. Si el valor no es una bandera conocida, se deja igual para que falle la validación.
     */
    public static function codigoParaGuardar(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return self::tryNormalize($value)?->value ?? $value;
    }
}
