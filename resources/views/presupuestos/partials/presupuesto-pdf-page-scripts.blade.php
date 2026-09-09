@php
    use App\Support\PresupuestoPdf;

    /** @var \App\Support\PresupuestoPdfDocumentConfig $pdf */
    /** @var array<string, mixed> $presupuesto */

    $margenMm = $pdf->margenContenidoLateralMm();
    $footerHeightMm = $pdf->footerHeightMm();
    $footerBottomMm = $pdf->footerBottomMm();
    $gapAtentamenteFooterMm = $pdf->gapAtentamenteFooterMm();
    $espacioTrasTituloAtentamenteMm = $pdf->espacioTrasTituloAtentamenteMm();
    $pdfVariant = $pdf->layoutVariantKey();
    $paginaAtentamente = max(0, (int) ($paginaAtentamente ?? 0));
    $paginasTrasSeccionPresupuesto = max(0, (int) ($paginasTrasSeccionPresupuesto ?? 0));

    $atentamentePieLineas = PresupuestoPdf::lineasAtentamentePieUltimaPaginaDesdePayload($presupuesto);
    $atentamenteEstilos = PresupuestoPdf::estilosAtentamentePiePorRol($pdfVariant, $pdf->themeVariablesArray());
    $atentamentePieX = (int) round($margenMm * 2.834645669);

    $mmToPt = static fn (float $mm): int => (int) round($mm * 2.834645669);

    $atentamentePageScript = '';
    if ($pdf->atentamenteEnPiePageScript() && count($atentamentePieLineas) > 0) {
        $blockHeightPt = 6;
        $espacioTrasTituloPt = $mmToPt($espacioTrasTituloAtentamenteMm);
        $tieneLineasTrasTitulo = count($atentamentePieLineas) > 1;
        foreach ($atentamentePieLineas as $linea) {
            $role = $linea['role'] ?? 'info';
            $blockHeightPt += (int) ceil($atentamenteEstilos[$role]['lh'] ?? 9.0);
        }
        if ($tieneLineasTrasTitulo) {
            $blockHeightPt += $espacioTrasTituloPt;
        }
        $footerReservePt = $mmToPt($footerBottomMm + $footerHeightMm + $gapAtentamenteFooterMm);
        $estilosJsonEsc = addcslashes(json_encode($atentamenteEstilos, JSON_UNESCAPED_UNICODE), "\\'");

        $jsonEsc = addcslashes(
            json_encode($atentamentePieLineas, JSON_UNESCAPED_UNICODE),
            "\\'"
        );
        // Ancla al final de la sección presupuesto (antes de anexos/documentación),
        // no a la estimación PHP: evita pintar Atte sobre la hoja de anexos.
        $paginasTrasPhp = $paginasTrasSeccionPresupuesto;
        $paginaAtentamenteFallback = max(1, $paginaAtentamente);
        $atentamentePageScript = <<<SCRIPT
\$paginasTras = {$paginasTrasPhp};
\$paginaAtte = \$PAGE_COUNT - \$paginasTras;
if (\$paginaAtte < 1) {
    \$paginaAtte = {$paginaAtentamenteFallback};
}
if (\$PAGE_NUM != \$paginaAtte) {
    return;
}
\$lineas = json_decode('{$jsonEsc}', true);
\$estilos = json_decode('{$estilosJsonEsc}', true);
if (!is_array(\$lineas) || !is_array(\$estilos)) {
    return;
}
\$x = {$atentamentePieX};
\$pageHeight = \$pdf->get_height();
\$y = \$pageHeight - {$footerReservePt} - {$blockHeightPt};
foreach (\$lineas as \$i => \$item) {
    if (!is_array(\$item)) {
        continue;
    }
    \$text = isset(\$item['text']) ? (string) \$item['text'] : '';
    if (\$text === '') {
        continue;
    }
    \$role = isset(\$item['role']) ? (string) \$item['role'] : 'info';
    if (\$role === 'title') {
        \$text = mb_strtoupper(\$text, 'UTF-8');
    }
    if (!isset(\$estilos[\$role])) {
        \$role = 'info';
    }
    \$cfg = \$estilos[\$role];
    \$size = (float) (\$cfg['size'] ?? 7);
    \$lh = (float) (\$cfg['lh'] ?? 9);
    \$color = \$cfg['color'] ?? array(0.067, 0.094, 0.153);
    if (!is_array(\$color) || count(\$color) < 3) {
        \$color = array(0.067, 0.094, 0.153);
    }
    \$bold = !empty(\$cfg['bold']);
    \$font = \$bold
        ? \$fontMetrics->getFont('DejaVu Sans', 'bold')
        : \$fontMetrics->getFont('DejaVu Sans', 'normal');
    \$pdf->text(\$x, \$y, \$text, \$font, \$size, array((float) \$color[0], (float) \$color[1], (float) \$color[2]));
    \$y += \$lh;
    if (\$role === 'title' && \$i < count(\$lineas) - 1) {
        \$y += {$espacioTrasTituloPt};
    }
}
SCRIPT;
    }

    $atentamentePageScriptExport = var_export($atentamentePageScript, true);

    $subencabezadoPageScript = '';
    if ($pdf->mostrarSubencabezadoCompacto()) {
        $subencabezadoPageScript = PresupuestoPdf::generarPageScriptSubencabezadoPresupuesto(
            $margenMm,
            $paginasTrasSeccionPresupuesto,
            $presupuesto,
            $pdfVariant,
            false,
            PresupuestoPdf::prepararLogoParaPageScript($presupuesto),
        );
    }
    $subencabezadoPageScriptExport = var_export($subencabezadoPageScript, true);
@endphp
<script type="text/php">
if (isset($pdf) && isset($fontMetrics)) {
    $font = $fontMetrics->getFont("DejaVu Sans", "normal");
    $size = 7;
    $sample = "Página 99 de 99";
    $width = $fontMetrics->getTextWidth($sample, $font, $size);
    $x = ($pdf->get_width() - $width) / 2 + 5;
    $pdf->page_text($x, $pdf->get_height() - 45, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, $size);

    $atentamentePieScript = {!! $atentamentePageScriptExport !!};
    if ($atentamentePieScript !== '') {
        $pdf->page_script($atentamentePieScript);
    }

    $subencabezadoScript = {!! $subencabezadoPageScriptExport !!};
    if ($subencabezadoScript !== '') {
        $pdf->page_script($subencabezadoScript);
    }
}
</script>
