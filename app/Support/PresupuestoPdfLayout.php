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
     *     reserva_pie_html_mm: float,
     *     firma_en_flujo: bool,
     *     altura_util_mm?: float,
     *     altura_atentamente_mm?: float,
     *     altura_terminos_mm?: float,
     *     resto_antes_terminos_mm?: float
     * }
     */
    public static function calcularCierreAtentamente(array $payload, PresupuestoPdfDocumentConfig $pdf): array
    {
        $vacio = [
            'salto_pagina_antes' => false,
            'pagina_atentamente' => 0,
            'reserva_pie_html_mm' => 0.0,
            'firma_en_flujo' => false,
        ];

        if (! PresupuestoPdf::debeMostrarBloqueAtentamenteDesdePayload($payload)) {
            return $vacio;
        }

        $alturaAtentamente = self::alturaAtentamentePintadaMm($payload, $pdf);
        // Capacidad = área HTML útil de DomPDF (caja @page − paddings del body).
        // La firma se pinta en el padding inferior; el HTML no entra ahí.
        $alturaUtil = $pdf->alturaUtilContenidoHtmlMm();
        $alturaTerminos = self::alturaTotalTerminosMm($payload, $pdf->layoutVariantKey());

        $variant = $pdf->layoutVariantKey();
        $flujo = self::simularFlujoHastaFinTerminos($payload, $pdf, $variant, $alturaUtil);
        $pageFin = max(1, (int) $flujo['page']);
        $yFin = (float) $flujo['y'];
        $pageAntes = max(1, (int) ($flujo['page_antes_terminos'] ?? $pageFin));
        $yAntes = (float) ($flujo['y_antes_terminos'] ?? $yFin);
        $restoEnPaginaAntes = max(0.0, $alturaUtil - $yAntes);

        // Solo forzar hoja nueva si los términos no caben enteros en el resto
        // real de la página actual (evita dejar hueco bajo totales).
        $terminosNoCabenEnResto = $pageAntes === $pageFin
            && $alturaTerminos > 0.05
            && $alturaTerminos > ($restoEnPaginaAntes + 0.8);
        $terminosYaPartidos = $pageFin > $pageAntes;
        $salto = $terminosYaPartidos || $terminosNoCabenEnResto;
        $paginaAtentamente = $salto ? $pageAntes + 1 : $pageFin;
        $reservaPieMm = 0.0;

        // La firma la pinta el page_script al pie de la última hoja del presupuesto.
        return [
            'salto_pagina_antes' => $salto,
            'pagina_atentamente' => $paginaAtentamente,
            'reserva_pie_html_mm' => $salto ? 0.0 : round($reservaPieMm, 2),
            'firma_en_flujo' => false,
            // Diagnóstico interno (no usado por Blade hoy).
            'altura_util_mm' => round($alturaUtil, 2),
            'altura_atentamente_mm' => round($alturaAtentamente, 2),
            'altura_terminos_mm' => round($alturaTerminos, 2),
            'resto_antes_terminos_mm' => round($restoEnPaginaAntes, 2),
        ];
    }

    /**
     * Simula paginación vertical: bloques fijos, tabla fila a fila
     * y términos ítem a ítem (sin partir cada enunciado).
     *
     * @param  array<string, mixed>  $payload
     * @return array{page: int, y: float, altura_total: float, page_antes_terminos: int, y_antes_terminos: float}
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
            $consumir($variant === 'tailwind' ? 1.0 : 3.0);
            $consumir((float) $secciones['descripcion_general']);
        }
        $consumir($variant === 'tailwind' ? 2.0 : 4.0);
        $consumir((float) ($secciones['titulo_presupuesto'] ?? 0));
        // margin-bottom de la tabla (~6 mm tailwind) + aire previo.
        $consumir($variant === 'tailwind' ? 6.0 : 3.0);

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
                $consumirFilaTabla(self::alturaFilaConceptoMm($concepto, $lineaTabla, $payload));
            }
        }

        // after-table-space--compact ≈ 3 mm.
        $consumir($variant === 'tailwind' ? 3.0 : 3.0);
        if (($payload['config_mostrar_totales'] ?? true) && ($secciones['totales'] ?? 0) > 0) {
            $consumir((float) $secciones['totales'], true);
        }

        $pageAntesTerminos = $pageNum;
        $yAntesTerminos = $y;

        foreach (self::fragmentosVerticalesTerminosMm($payload, $variant) as $fragmento) {
            $consumir($fragmento['altura_mm'], $fragmento['evitar_corte']);
        }

        return [
            'page' => $pageNum,
            'y' => $y,
            'altura_total' => $alturaTotal,
            'page_antes_terminos' => $pageAntesTerminos,
            'y_antes_terminos' => $yAntesTerminos,
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
        // Tipografía real ~6.2pt con line-height compacto.
        $lineaTermino = 3.4;
        $fragmentos = [];

        $hayAlguno = count($terminos) > 0 || count($validaciones) > 0 || count($observaciones) > 0;
        if (! $hayAlguno) {
            return $fragmentos;
        }

        $fragmentos[] = ['altura_mm' => $variant === 'tailwind' ? 2.0 : 3.0, 'evitar_corte' => false];

        if (count($terminos) > 0) {
            $fragmentos[] = ['altura_mm' => 4.5, 'evitar_corte' => true];
            foreach ($terminos as $texto) {
                $fragmentos[] = [
                    'altura_mm' => self::alturaTextoMm((string) $texto, 100, $lineaTermino) + 0.4,
                    'evitar_corte' => true,
                ];
            }
        }
        if (count($validaciones) > 0) {
            $fragmentos[] = ['altura_mm' => 4.0, 'evitar_corte' => true];
            foreach ($validaciones as $item) {
                $fragmentos[] = [
                    'altura_mm' => self::alturaTextoMm((string) $item, 100, $lineaTermino) + 0.4,
                    'evitar_corte' => true,
                ];
            }
        }
        if (count($observaciones) > 0) {
            $fragmentos[] = ['altura_mm' => 4.0, 'evitar_corte' => true];
            foreach ($observaciones as $obs) {
                $fragmentos[] = [
                    'altura_mm' => self::alturaTextoMm((string) $obs, 100, $lineaTermino) + 0.4,
                    'evitar_corte' => true,
                ];
            }
        }

        return $fragmentos;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function alturaTotalTerminosMm(array $payload, string $variant): float
    {
        $total = 0.0;
        foreach (self::fragmentosVerticalesTerminosMm($payload, $variant) as $fragmento) {
            $total += (float) ($fragmento['altura_mm'] ?? 0);
        }

        return $total;
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
     * Alto de una fila de concepto, con imagen (23 mm) y desglose de matriz si el PDF lo muestra.
     *
     * @param  array<string, mixed>  $concepto
     * @param  array<string, mixed>  $payload
     */
    private static function alturaFilaConceptoMm(array $concepto, float $lineaTabla, array $payload): float
    {
        if (self::esConceptoParrafo($concepto)) {
            return PresupuestoParrafoPdf::alturaFilaDesdeConcepto($concepto);
        }

        $altura = $lineaTabla;
        $imagen = $concepto['imagen_base64'] ?? '';
        // CSS real: tr.linea-con-imagen { height: 18mm }
        if (is_string($imagen) && $imagen !== '') {
            $altura = max($altura, 18.0);
        }

        $mostrarMatriz = (bool) ($payload['config_mostrar_matriz_costos'] ?? false);
        $componentes = is_array($concepto['componentes'] ?? null) ? $concepto['componentes'] : [];
        if ($mostrarMatriz && ($concepto['tiene_matriz'] ?? false) && $componentes !== []) {
            foreach ($componentes as $comp) {
                if (! is_array($comp)) {
                    continue;
                }
                $unidad = mb_strtolower(trim((string) ($comp['unidad'] ?? '')));
                if ($unidad === 'párrafo' || $unidad === 'parrafo') {
                    $altura += self::alturaTextoMm((string) ($comp['descripcion'] ?? ''), 90, 3.6) + 5.0;
                } else {
                    $altura += 9.0;
                }
            }
        }

        return $altura;
    }

    /**
     * Alto del bloque que pinta el page_script, en mm (mismos interlineados en pt).
     *
     * @param  array<string, mixed>  $payload
     */
    private static function alturaAtentamentePintadaMm(array $payload, PresupuestoPdfDocumentConfig $pdf): float
    {
        $lineas = PresupuestoPdf::lineasAtentamentePieUltimaPaginaDesdePayload($payload);
        if (count($lineas) === 0) {
            return 0.0;
        }

        $estilos = PresupuestoPdf::estilosAtentamentePiePorRol(
            $pdf->layoutVariantKey(),
            $pdf->themeVariablesArray()
        );
        $pt = 6.0;
        foreach ($lineas as $linea) {
            $role = (string) ($linea['role'] ?? 'info');
            $pt += (float) ($estilos[$role]['lh'] ?? 9.0);
        }
        if (count($lineas) > 1) {
            $pt += $pdf->espacioTrasTituloAtentamenteMm() * 2.834645669;
        }

        return $pt / 2.834645669;
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

        $lineaTabla = $variant === 'tailwind' ? 6.8 : 5.5;

        $tablaMm = 6.0;
        foreach ($conceptos as $concepto) {
            if (! is_array($concepto)) {
                continue;
            }
            $tablaMm += self::alturaFilaConceptoMm($concepto, $lineaTabla, $payload);
        }
        if (count($conceptos) === 0) {
            $tablaMm += 10.0;
        }

        $terminosMm = self::alturaTotalTerminosMm($payload, $variant);

        $receptorLineas = $payload['receptor_lineas'] ?? [];
        $receptorMm = 5.0 + max(1, count($receptorLineas)) * 4.2;

        $conceptoGeneral = trim((string) ($payload['concepto_general'] ?? ''));
        $descripcionMm = $conceptoGeneral !== ''
            ? 7.0 + self::alturaTextoMm($conceptoGeneral, 88, 3.8)
            : 0.0;

        $totalesMm = 0.0;
        if ($payload['config_mostrar_totales'] ?? true) {
            $filasIndirectos = count(app(\App\Services\Presupuesto\PresupuestoMatrizCalculoService::class)
                ->filasIndirectosDocumento($payload));
            // Subtotal + total + importe con letra (~22 mm) + IVA/descuento opcionales.
            $filasBase = 3; // subtotal, total, importe letra
            if (! empty($payload['con_iva'])) {
                $filasBase++;
            }
            $tieneDescuento = ((int) ($payload['porcentaje_descuento'] ?? 0) >= 1)
                || ((float) ($payload['cantidad_descuento'] ?? 0) > 0);
            if ($tieneDescuento) {
                $filasBase++;
            }
            $totalesMm = 10.0 + ($filasBase * 5.2) + ($filasIndirectos * 5.0) + ($filasIndirectos > 0 ? 2.0 : 0.0);
        }

        return [
            'encabezado' => $variant === 'tailwind' ? 34.0 : 36.0,
            'receptor' => $receptorMm,
            'descripcion_general' => $descripcionMm,
            'titulo_presupuesto' => $variant === 'tailwind' ? 4.0 : 11.0,
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
