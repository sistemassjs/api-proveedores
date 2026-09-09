<?php

namespace App\Support;

/**
 * Estimación de alturas (mm) del cuerpo presupuesto para alinear «Atentamente» al pie de la última hoja de la sección 1.
 */
final class PresupuestoPdfLayout
{
    private const THEAD_TABLA_MM = 6.0;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     salto_pagina_antes: bool,
     *     pagina_atentamente: int,
     *     reserva_pie_html_mm: float
     * }
     */
    public static function calcularCierreAtentamente(array $payload, PresupuestoPdfDocumentConfig $pdf): array
    {
        $vacio = [
            'salto_pagina_antes' => false,
            'pagina_atentamente' => 0,
            'reserva_pie_html_mm' => 0.0,
        ];

        if (! PresupuestoPdf::debeMostrarBloqueAtentamenteDesdePayload($payload)) {
            return $vacio;
        }

        $medidas = $pdf->medidasSimulacionPaginaMm();
        $alturaUtil = $medidas['altura_util_mm'];
        $gapPie = $medidas['gap_atentamente_footer_mm'];

        $alturaAtentamente = self::estimarAlturaBloqueAtentamenteMm($payload, $medidas);
        $reservaPieMm = $alturaAtentamente + $gapPie + 2.0;

        $variant = $pdf->layoutVariantKey();
        $flujo = self::simularFlujoHastaFinTerminos($payload, $pdf, $variant, $alturaUtil);
        $pageFin = max(1, (int) $flujo['page']);
        $yFin = (float) $flujo['y'];
        $capFin = self::capacidadPaginaMm($pageFin, $alturaUtil, $pdf);
        $salto = ($yFin + $reservaPieMm + $pdf->margenSeguridadAtentamenteMm()) > $capFin;
        $paginaAtentamente = $salto ? $pageFin + 1 : $pageFin;

        // Con page_script el Atte se pinta en el pie; si no cabe, saltar hoja.
        // La página final real se recalcula en el script con PAGE_COUNT - anexos.
        return [
            'salto_pagina_antes' => $salto,
            'pagina_atentamente' => $paginaAtentamente,
            'reserva_pie_html_mm' => $salto ? 0.0 : round($reservaPieMm, 2),
        ];
    }

    /**
     * Simula paginación vertical: bloques fijos, tabla fila a fila
     * y términos ítem a ítem (sin partir cada enunciado).
     *
     * @param  array<string, mixed>  $payload
     * @return array{page: int, y: float, altura_total: float}
     */
    private static function simularFlujoHastaFinTerminos(
        array $payload,
        PresupuestoPdfDocumentConfig $pdf,
        string $variant,
        float $alturaUtil
    ): array {
        $pageNum = 1;
        $y = 0.0;
        $alturaTotal = 0.0;

        $consumir = static function (float $h, bool $evitarCorte = false) use (&$pageNum, &$y, &$alturaTotal, $alturaUtil, $pdf): void {
            if ($h <= 0) {
                return;
            }
            $pendiente = $h;
            while ($pendiente > 0.01) {
                $cap = self::capacidadPaginaMm($pageNum, $alturaUtil, $pdf);
                $resto = $cap - $y;
                if ($evitarCorte && $pendiente > $resto && $y > 0.01) {
                    $pageNum++;
                    $y = 0.0;

                    continue;
                }
                if ($pendiente <= $resto + 0.01) {
                    $y += $pendiente;
                    $alturaTotal += $pendiente;
                    $pendiente = 0.0;

                    continue;
                }
                if ($y > 0.01) {
                    $alturaTotal += $resto;
                    $pendiente -= $resto;
                    $pageNum++;
                    $y = 0.0;

                    continue;
                }
                $alturaTotal += $cap;
                $pendiente -= $cap;
                $pageNum++;
                $y = 0.0;
            }
        };

        $consumirFilaTabla = static function (float $h) use (&$pageNum, &$y, &$alturaTotal, $alturaUtil, $pdf): void {
            if ($h <= 0) {
                return;
            }
            $resto = self::capacidadPaginaMm($pageNum, $alturaUtil, $pdf) - $y;
            if ($h > $resto && $y > 0.01) {
                $pageNum++;
                $y = self::THEAD_TABLA_MM;
                $alturaTotal += self::THEAD_TABLA_MM;
            }
            $y += $h;
            $alturaTotal += $h;
        };

        $secciones = self::estimarSeccionesMm($payload, $variant);

        $consumir((float) ($secciones['encabezado'] ?? 0));
        $consumir((float) ($secciones['receptor'] ?? 0));
        if (($secciones['descripcion_general'] ?? 0) > 0) {
            $consumir(3.0);
            $consumir((float) $secciones['descripcion_general']);
        }
        $consumir(4.0);
        $consumir((float) ($secciones['titulo_presupuesto'] ?? 0));
        $consumir(3.0);

        $conceptos = $payload['conceptos'] ?? [];
        $lineaTabla = $pdf->lineaTablaMm();
        $consumir(self::THEAD_TABLA_MM);
        if (count($conceptos) === 0) {
            $consumirFilaTabla(10.0);
        } else {
            foreach ($conceptos as $concepto) {
                if (! is_array($concepto)) {
                    continue;
                }
                if (self::esConceptoParrafo($concepto)) {
                    $consumirFilaTabla(PresupuestoParrafoPdf::alturaFilaDesdeConcepto($concepto));
                } else {
                    $consumirFilaTabla($lineaTabla);
                }
            }
        }

        $consumir(3.0);
        if (($payload['config_mostrar_totales'] ?? true) && ($secciones['totales'] ?? 0) > 0) {
            $consumir((float) $secciones['totales'], true);
        }

        foreach (self::fragmentosVerticalesTerminosMm($payload, $variant) as $fragmento) {
            $consumir($fragmento['altura_mm'], $fragmento['evitar_corte']);
        }

        return [
            'page' => $pageNum,
            'y' => $y,
            'altura_total' => $alturaTotal,
        ];
    }

    /**
     * Fragmentos verticales de términos / observaciones (un ítem = un bloque indivisible).
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{altura_mm: float, evitar_corte: bool}>
     */
    private static function fragmentosVerticalesTerminosMm(array $payload, string $variant): array
    {
        $terminos = $payload['terminos_enunciados'] ?? [];
        $validaciones = $payload['validaciones_enunciados'] ?? [];
        $observaciones = $payload['observaciones_enunciados'] ?? [];
        $lineaTermino = 4.2;
        $fragmentos = [];

        $hayAlguno = count($terminos) > 0 || count($validaciones) > 0 || count($observaciones) > 0;
        if (! $hayAlguno) {
            return $fragmentos;
        }

        $fragmentos[] = ['altura_mm' => 4.0, 'evitar_corte' => true];

        if (count($terminos) > 0) {
            $fragmentos[] = ['altura_mm' => 6.5, 'evitar_corte' => true];
            foreach ($terminos as $texto) {
                $fragmentos[] = [
                    'altura_mm' => self::alturaTextoMm((string) $texto, 95, $lineaTermino) + 0.6,
                    'evitar_corte' => true,
                ];
            }
        }
        if (count($validaciones) > 0) {
            $fragmentos[] = ['altura_mm' => 5.0, 'evitar_corte' => true];
            foreach ($validaciones as $item) {
                $fragmentos[] = [
                    'altura_mm' => self::alturaTextoMm((string) $item, 95, $lineaTermino) + 0.6,
                    'evitar_corte' => true,
                ];
            }
        }
        if (count($observaciones) > 0) {
            $fragmentos[] = ['altura_mm' => 5.0, 'evitar_corte' => true];
            foreach ($observaciones as $obs) {
                $fragmentos[] = [
                    'altura_mm' => self::alturaTextoMm((string) $obs, 95, $lineaTermino) + 0.6,
                    'evitar_corte' => true,
                ];
            }
        }

        if ($variant === 'tailwind' && count($fragmentos) > 1) {
            $fragmentos[0]['altura_mm'] = 3.0;
        }

        return $fragmentos;
    }

    private static function capacidadPaginaMm(int $pageNum, float $alturaUtil, PresupuestoPdfDocumentConfig $pdf): float
    {
        return $alturaUtil;
    }

    /**
     * @param  array<string, mixed>  $concepto
     */
    private static function esConceptoParrafo(array $concepto): bool
    {
        return PresupuestoParrafoPdf::esLineaParrafo($concepto);
    }

    /**
     * @deprecated Use {@see PresupuestoPdfDocumentConfig::medidasSimulacionPaginaMm()}
     *
     * @return array{
     *     altura_util_mm: float,
     *     gap_atentamente_footer_mm: float,
     *     espacio_tras_titulo_atentamente_mm: float
     * }
     */
    public static function medidasHojaMm(string $variant = 'default'): array
    {
        return self::forLayoutVariant($variant === 'tailwind' ? 'tailwind' : 'default')
            ->medidasSimulacionPaginaMm();
    }

    /**
     * Alturas estimadas por bloque (sin términos ni Atentamente).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    public static function estimarSeccionesMm(array $payload, string $variant = 'default'): array
    {
        $conceptos = $payload['conceptos'] ?? [];
        $terminos = $payload['terminos_enunciados'] ?? [];
        $validaciones = $payload['validaciones_enunciados'] ?? [];
        $observaciones = $payload['observaciones_enunciados'] ?? [];

        $lineaTabla = $variant === 'tailwind' ? 6.8 : 5.5;
        $lineaTermino = 4.2;

        $tablaMm = 6.0;
        foreach ($conceptos as $concepto) {
            if (! is_array($concepto)) {
                continue;
            }
            if (self::esConceptoParrafo($concepto)) {
                $tablaMm += PresupuestoParrafoPdf::alturaFilaDesdeConcepto($concepto);
            } else {
                $tablaMm += $lineaTabla;
            }
        }
        if (count($conceptos) === 0) {
            $tablaMm += 10.0;
        }

        $terminosMm = 0.0;
        if (count($terminos) > 0 || count($validaciones) > 0 || count($observaciones) > 0) {
            $terminosMm += 4.0;
        }
        if (count($terminos) > 0) {
            $terminosMm += 6.5;
            foreach ($terminos as $texto) {
                $terminosMm += self::alturaTextoMm((string) $texto, 95, $lineaTermino);
            }
        }
        if (count($validaciones) > 0) {
            $terminosMm += 5.0 + count($validaciones) * $lineaTermino;
        }
        if (count($observaciones) > 0) {
            $terminosMm += 5.0;
            foreach ($observaciones as $obs) {
                $terminosMm += self::alturaTextoMm((string) $obs, 95, $lineaTermino);
            }
        }

        $receptorLineas = $payload['receptor_lineas'] ?? [];
        $receptorMm = 5.0 + max(1, count($receptorLineas)) * 4.2;

        $conceptoGeneral = trim((string) ($payload['concepto_general'] ?? ''));
        $descripcionMm = $conceptoGeneral !== ''
            ? 7.0 + self::alturaTextoMm($conceptoGeneral, 88, 3.8)
            : 0.0;

        $totalesMm = 0.0;
        if ($payload['config_mostrar_totales'] ?? true) {
            $totalesMm = 32.0;
        }

        return [
            'encabezado' => $variant === 'tailwind' ? 36.0 : 38.0,
            'receptor' => $receptorMm,
            'descripcion_general' => $descripcionMm,
            'titulo_presupuesto' => 11.0,
            'tabla_conceptos' => $tablaMm,
            'totales' => $totalesMm,
            'terminos_y_observaciones' => $terminosMm,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, float>  $medidas
     */
    private static function estimarAlturaBloqueAtentamenteMm(array $payload, array $medidas): float
    {
        $lineas = PresupuestoPdf::lineasAtentamentePieUltimaPaginaDesdePayload($payload);
        if (count($lineas) === 0) {
            return 0.0;
        }

        $altura = 4.0;
        $trasTitulo = (float) $medidas['espacio_tras_titulo_atentamente_mm'];
        $tieneLineasTrasTitulo = count($lineas) > 1;

        foreach ($lineas as $i => $linea) {
            $role = (string) ($linea['role'] ?? 'info');
            $altura += match ($role) {
                'title' => 4.5,
                'name' => 4.8,
                default => 3.8,
            };
            if ($role === 'title' && $tieneLineasTrasTitulo && $i < count($lineas) - 1) {
                $altura += $trasTitulo;
            }
        }

        return $altura + 1.5;
    }

    private static function alturaTextoMm(string $text, float $charsPorLinea, float $alturaLineaMm): float
    {
        $text = trim($text);
        if ($text === '') {
            return 0.0;
        }

        $charsPorLinea = max(20.0, $charsPorLinea);
        $lineas = (int) ceil(mb_strlen($text) / $charsPorLinea);

        return max(1, $lineas) * $alturaLineaMm;
    }
}
