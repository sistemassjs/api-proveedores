<?php

namespace App\Support;

use App\Models\PresupuestoConcepto;

/**
 * Texto y altura de líneas tipo párrafo en el PDF del presupuesto.
 * La altura se calcula por renglones (máx. ~9) para no reservar espacio en blanco.
 */
final class PresupuestoParrafoPdf
{
    public const CHARS_POR_LINEA = 120;

    public const MAX_LINEAS = 9;

    /** Padding vertical de la fila (2 mm arriba + 2 mm abajo). */
    public const ALTURA_PADDING_MM = 4.0;

    /** Altura estimada de un renglón a 6.5pt / line-height 1.45. */
    public const ALTURA_LINEA_MM = 3.4;

    public const DESCRIPCION_MAX = PresupuestoConcepto::DESCRIPCION_PARRAFO_MAX;

    /** Tope de reserva (padding + MAX_LINEAS renglones). */
    public const ALTURA_FILA_MAX_MM = 34.6;

    public static function alturaFilaMm(string $descripcion = ''): float
    {
        $texto = self::sanitizarTexto($descripcion);
        $lineas = (int) ceil(mb_strlen($texto) / self::CHARS_POR_LINEA);
        $lineas = max(1, min(self::MAX_LINEAS, $lineas));
        $altura = self::ALTURA_PADDING_MM + ($lineas * self::ALTURA_LINEA_MM);

        return min(self::ALTURA_FILA_MAX_MM, $altura);
    }

    /**
     * @param  array<string, mixed>  $concepto
     */
    public static function alturaFilaDesdeConcepto(array $concepto): float
    {
        return self::alturaFilaMm((string) ($concepto['descripcion'] ?? ''));
    }

    /**
     * Normaliza texto de párrafo (sin saltos de línea; espacios colapsados).
     */
    public static function normalizarTexto(string $text): string
    {
        $result = preg_replace('/\R/u', ' ', $text) ?? $text;
        $result = preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', '', $result) ?? $result;
        $result = preg_replace('/ +/u', ' ', $result) ?? $result;

        return trim($result);
    }

    /**
     * Texto listo para guardar o mostrar en PDF (normalizado y acotado al bloque impreso).
     */
    public static function sanitizarTexto(string $text): string
    {
        $normalizado = self::normalizarTexto($text);
        if ($normalizado === '') {
            return '';
        }

        if (mb_strlen($normalizado) <= self::DESCRIPCION_MAX) {
            return $normalizado;
        }

        return mb_substr($normalizado, 0, self::DESCRIPCION_MAX);
    }

    /**
     * @param  array<string, mixed>  $concepto
     */
    public static function esLineaParrafo(array $concepto): bool
    {
        if (($concepto['tipo'] ?? PresupuestoConcepto::TIPO_CONCEPTO) === PresupuestoConcepto::TIPO_PARRAFO) {
            return true;
        }

        return mb_strtolower(trim((string) ($concepto['unidad'] ?? ''))) === 'párrafo';
    }

    /**
     * Descripción para celdas del PDF (mismo criterio que almacenamiento).
     */
    public static function descripcionParaPdf(array $concepto): string
    {
        return self::sanitizarTexto((string) ($concepto['descripcion'] ?? ''));
    }
}
