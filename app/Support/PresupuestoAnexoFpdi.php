<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;

/**
 * FPDI con utilidades gráficas para estampado de anexos PDF.
 */
final class PresupuestoAnexoFpdi extends Fpdi
{
    /**
     * Elipse o círculo (script FPDF #37).
     *
     * @param  'D'|'F'|'FD'|'DF'  $style
     */
    public function elipse(float $x, float $y, float $rx, float $ry, string $style = 'D'): void
    {
        $op = match ($style) {
            'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S',
        };

        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;
        $k = $this->k;
        $h = $this->h;
        $x *= $k;
        $rx *= $k;
        $y = ($h - $y) * $k;
        $ry *= $k;

        $path = sprintf(
            '%.3F %.3F m %.3F %.3F %.3F %.3F %.3F %.3F c',
            $x + $rx,
            $y,
            $x + $rx,
            $y - $ly,
            $x + $lx,
            $y - $ry,
            $x,
            $y - $ry
        );
        $path .= sprintf(
            ' %.3F %.3F %.3F %.3F %.3F %.3F c',
            $x - $lx,
            $y - $ry,
            $x - $rx,
            $y - $ly,
            $x - $rx,
            $y
        );
        $path .= sprintf(
            ' %.3F %.3F %.3F %.3F %.3F %.3F c',
            $x - $rx,
            $y + $ly,
            $x - $lx,
            $y + $ry,
            $x,
            $y + $ry
        );
        $path .= sprintf(
            ' %.3F %.3F %.3F %.3F %.3F %.3F c %s',
            $x + $lx,
            $y + $ry,
            $x + $rx,
            $y + $ly,
            $x + $rx,
            $y,
            $op
        );

        $this->_out($path);
    }

    public function circulo(float $x, float $y, float $r, string $style = 'D'): void
    {
        $this->elipse($x, $y, $r, $r, $style);
    }
}
