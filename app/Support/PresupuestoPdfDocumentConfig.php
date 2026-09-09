<?php

namespace App\Support;

use App\Services\Presupuesto\PresupuestoThemeService;

/**
 * Configuración central del PDF DomPDF (layout, secciones, plantilla, tema).
 * Punto único para plantillas Blade y simulación de paginación.
 */
final class PresupuestoPdfDocumentConfig
{
    public const TEMPLATE_TAILWIND = 'tailwind';

    public const TEMPLATE_CLASSIC = 'classic';

    public const MARGEN_HOJA_MM = 25.5;

    public const FOOTER_HEIGHT_MM = 25.4;

    public const FOOTER_BOTTOM_MM = 6.0;

    public const LINEA_MM = 2.8;

    public const BODY_PADDING_BOTTOM_EXTRA_MM = 8.0;

    public const MARGEN_SEGURIDAD_ATENTAMENTE_MM = 3.0;

    public const SUBENCABEZADO_ALTURA_MM = 24.0;

    public const SEPARACION_TRAS_SUBENCABEZADO_MM = 3.0;

    public const ANEXOS_IMAGENES_POR_PAGINA = 4;

    /** Separación logo ↔ información general del encabezado (mm). */
    public const GAP_LOGO_INFO_MM = 7.0;

    /** Separación de la regla bajo el encabezado (mm). */
    public const GAP_HEADER_RULE_MM = 3.0;

    /**
     * Keys admitidas en presupuestos.ppto_config (JSON plano).
     *
     * @var list<string>
     */
    public const PPTO_CONFIG_KEYS = [
        'margen_hoja_mm',
        'margen_lateral_mm',
        'margen_superior_mm',
        'gap_logo_info_mm',
        'gap_header_rule_mm',
        'footer_height_mm',
        'gap_atentamente_footer_mm',
        'espacio_tras_titulo_atentamente_mm',
    ];

    private const ALTURA_HOJA_LETRA_MM = 279.4;

    private const MARGEN_CONTENIDO_TAILWIND_MM = 20.0;

    private const MARGEN_CONTENIDO_CLASSIC_MM = 25.4;

    private const LINEA_TABLA_TAILWIND_MM = 6.8;

    private const LINEA_TABLA_CLASSIC_MM = 5.5;

    private const DOMPDF_MARGIN_BOTTOM_PT = 70;

    private const DOMPDF_DPI = 96;

    /** Subencabezado compacto (page_script) en presupuesto pág. 2+ y anexos imagen. */
    private const MOSTRAR_SUBENCABEZADO_COMPACTO = false;

    private const ATENTAMENTE_EN_PIE_PAGE_SCRIPT = true;

    private const DEBUG_BORDES_CONTENEDORES = false;

    private const DOCUMENTACION_USA_HEADER_COMPACTO = false;

    public function __construct(
        private readonly string $templateVariant,
        private readonly string $themeKey,
        private readonly PresupuestoThemeService $themeService,
        /** @var array<string, float> */
        private readonly array $layoutOverrides = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<string, float>
     */
    public static function sanitizePptoConfig(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach (self::PPTO_CONFIG_KEYS as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            if (! is_numeric($value)) {
                continue;
            }
            $float = (float) $value;
            if ($float < 0 || $float > 80) {
                continue;
            }
            $out[$key] = $float;
        }

        return $out;
    }

    private function layoutOverride(string $key): ?float
    {
        return array_key_exists($key, $this->layoutOverrides)
            ? $this->layoutOverrides[$key]
            : null;
    }

    /**
     * @param  array<string, mixed>  $presupuestoPayload
     */
    public static function forLayoutVariant(string $layoutVariantKey): self
    {
        $service = app(PresupuestoThemeService::class);
        $template = $layoutVariantKey === 'tailwind'
            ? self::TEMPLATE_TAILWIND
            : self::TEMPLATE_CLASSIC;

        return new self($template, PresupuestoThemeService::DEFAULT_THEME_KEY, $service);
    }

    public static function fromPresupuestoPayload(array $presupuestoPayload): self
    {
        $service = app(PresupuestoThemeService::class);
        $themeKey = $service->resolveThemeKey($presupuestoPayload['pdf_theme'] ?? null);

        return new self(
            self::resolveTemplateVariant(),
            $themeKey,
            $service,
            self::sanitizePptoConfig($presupuestoPayload['ppto_config'] ?? null),
        );
    }

    public static function defaults(): self
    {
        $service = app(PresupuestoThemeService::class);

        return new self(
            self::resolveTemplateVariant(),
            PresupuestoThemeService::DEFAULT_THEME_KEY,
            $service,
        );
    }

    public static function resolveTemplateVariant(): string
    {
        $configured = config('presupuestos.pdf_template', self::TEMPLATE_TAILWIND);

        return $configured === self::TEMPLATE_CLASSIC
            ? self::TEMPLATE_CLASSIC
            : self::TEMPLATE_TAILWIND;
    }

    public function viewName(): string
    {
        return $this->templateVariant === self::TEMPLATE_CLASSIC
            ? 'presupuestos.pdf'
            : 'presupuestos.pdf-tailwind';
    }

    /** Clave usada por {@see PresupuestoPdfLayout} (`tailwind` | `default`). */
    public function layoutVariantKey(): string
    {
        return $this->templateVariant === self::TEMPLATE_TAILWIND
            ? 'tailwind'
            : 'default';
    }

    public function templateVariant(): string
    {
        return $this->templateVariant;
    }

    public function themeKey(): string
    {
        return $this->themeKey;
    }

    public function mostrarSubencabezadoCompacto(): bool
    {
        return self::MOSTRAR_SUBENCABEZADO_COMPACTO;
    }

    public function documentacionUsaHeaderCompacto(): bool
    {
        return self::DOCUMENTACION_USA_HEADER_COMPACTO;
    }

    public function atentamenteEnPiePageScript(): bool
    {
        return self::ATENTAMENTE_EN_PIE_PAGE_SCRIPT;
    }

    public function debugBordesContenedores(): bool
    {
        return self::DEBUG_BORDES_CONTENEDORES;
    }

    public function anexosImagenesPorPagina(): int
    {
        return self::ANEXOS_IMAGENES_POR_PAGINA;
    }

    public function margenHojaMm(): float
    {
        return $this->layoutOverride('margen_hoja_mm') ?? self::MARGEN_HOJA_MM;
    }

    public function margenContenidoLateralMm(): float
    {
        $override = $this->layoutOverride('margen_lateral_mm');
        if ($override !== null) {
            return $override;
        }

        return $this->templateVariant === self::TEMPLATE_TAILWIND
            ? self::MARGEN_CONTENIDO_TAILWIND_MM
            : self::MARGEN_CONTENIDO_CLASSIC_MM;
    }

    public function margenSuperiorContenidoMm(): float
    {
        $override = $this->layoutOverride('margen_superior_mm');
        if ($override !== null) {
            return $override;
        }

        $margenLateral = $this->margenContenidoLateralMm();

        return max(8.0, $margenLateral - (4 * self::LINEA_MM));
    }

    public function footerHeightMm(): float
    {
        return $this->layoutOverride('footer_height_mm') ?? self::FOOTER_HEIGHT_MM;
    }

    public function footerBottomMm(): float
    {
        return self::FOOTER_BOTTOM_MM;
    }

    public function lineaMm(): float
    {
        return self::LINEA_MM;
    }

    public function gapAtentamenteFooterMm(): float
    {
        return $this->layoutOverride('gap_atentamente_footer_mm') ?? self::LINEA_MM;
    }

    public function espacioTrasTituloAtentamenteMm(): float
    {
        return $this->layoutOverride('espacio_tras_titulo_atentamente_mm') ?? (2 * self::LINEA_MM);
    }

    public function gapLogoInfoMm(): float
    {
        return $this->layoutOverride('gap_logo_info_mm') ?? self::GAP_LOGO_INFO_MM;
    }

    public function gapHeaderRuleMm(): float
    {
        return $this->layoutOverride('gap_header_rule_mm') ?? self::GAP_HEADER_RULE_MM;
    }

    public function bodyPaddingBottomMm(): float
    {
        return $this->footerHeightMm() + self::BODY_PADDING_BOTTOM_EXTRA_MM;
    }

    public function margenSeguridadAtentamenteMm(): float
    {
        return self::MARGEN_SEGURIDAD_ATENTAMENTE_MM;
    }

    public function lineaTablaMm(): float
    {
        return $this->templateVariant === self::TEMPLATE_TAILWIND
            ? self::LINEA_TABLA_TAILWIND_MM
            : self::LINEA_TABLA_CLASSIC_MM;
    }

    public function reservaSubencabezadoContinuacionMm(): float
    {
        if (! $this->mostrarSubencabezadoCompacto()) {
            return 0.0;
        }

        return self::SUBENCABEZADO_ALTURA_MM + self::SEPARACION_TRAS_SUBENCABEZADO_MM;
    }

    /**
     * @return array{
     *     altura_util_mm: float,
     *     gap_atentamente_footer_mm: float,
     *     espacio_tras_titulo_atentamente_mm: float
     * }
     */
    public function medidasSimulacionPaginaMm(): array
    {
        $margenSuperiorMm = $this->margenSuperiorContenidoMm();
        $margenPaginaMm = $this->margenHojaMm();

        $alturaUtil = self::ALTURA_HOJA_LETRA_MM
            - $margenSuperiorMm
            - $margenPaginaMm
            - $this->footerHeightMm()
            - self::FOOTER_BOTTOM_MM
            - $margenPaginaMm;

        return [
            'altura_util_mm' => max(165.0, $alturaUtil),
            'gap_atentamente_footer_mm' => self::LINEA_MM,
            'espacio_tras_titulo_atentamente_mm' => 2 * self::LINEA_MM,
        ];
    }

    public function themeCssVariables(): string
    {
        return $this->themeService->generateCssVariables($this->themeKey);
    }

    public function themeTableHeaderCss(): string
    {
        return $this->themeService->generateTableHeaderCss($this->themeKey);
    }

    /**
     * @return array{bg: string, text: string, border: string}
     */
    public function themeTableHeaderColors(): array
    {
        return $this->themeService->tableHeaderColors($this->themeKey);
    }

    public function themeTableHeaderCellStyle(): string
    {
        $colors = $this->themeTableHeaderColors();

        return 'background-color:'.$colors['bg'].';color:'.$colors['text'].';border:1px solid '.$colors['border'].';';
    }

    /**
     * @return array<string, string|float>|null
     */
    public function themeVariablesArray(): ?array
    {
        if ($this->templateVariant !== self::TEMPLATE_TAILWIND) {
            return null;
        }

        return $this->themeService->getTheme($this->themeKey)['variables'];
    }

    /**
     * Variables para plantillas Blade (márgenes, tema, secciones, cierre Atentamente).
     *
     * @param  array<string, mixed>  $presupuestoPayload
     * @return array<string, mixed>
     */
    public function bladeViewVariables(array $presupuestoPayload): array
    {
        $terminosLista = $presupuestoPayload['terminos_enunciados'] ?? [];
        $validacionesLista = $presupuestoPayload['validaciones_enunciados'] ?? [];
        $observacionesLista = $presupuestoPayload['observaciones_enunciados'] ?? [];
        $anexosLista = $presupuestoPayload['anexos'] ?? [];
        $documentacionLista = $presupuestoPayload['documentacion_adjuntos'] ?? [];
        $tituloAnexos = trim((string) ($presupuestoPayload['titulo_anexos'] ?? ''));
        if ($tituloAnexos === '') {
            $tituloAnexos = 'Anexos';
        }
        $haySeccionAnexos = count($anexosLista) > 0;
        $haySeccionDocumentacion = count($documentacionLista) > 0;
        $paginasAnexosPdf = $haySeccionAnexos
            ? (int) ceil(count($anexosLista) / max(1, $this->anexosImagenesPorPagina()))
            : 0;
        $paginasDocumentacionPdf = $haySeccionDocumentacion ? count($documentacionLista) : 0;

        return [
            'margenMm' => $this->margenContenidoLateralMm(),
            'margenPaginaMm' => $this->margenHojaMm(),
            'footerHeightMm' => $this->footerHeightMm(),
            'lineaEspacioMm' => $this->lineaMm(),
            'gapAtentamenteFooterMm' => $this->gapAtentamenteFooterMm(),
            'espacioTrasTituloAtentamenteMm' => $this->espacioTrasTituloAtentamenteMm(),
            'margenSuperiorMm' => $this->margenSuperiorContenidoMm(),
            'bodyPaddingBottomMm' => $this->bodyPaddingBottomMm(),
            'gapLogoInfoMm' => $this->gapLogoInfoMm(),
            'gapHeaderRuleMm' => $this->gapHeaderRuleMm(),
            'pdfDebugBordesContenedores' => $this->debugBordesContenedores(),
            'pdfThemeKey' => $this->themeKey(),
            'presupuestoThemeCss' => $this->themeCssVariables(),
            'presupuestoTableHeaderCss' => $this->themeTableHeaderCss(),
            'thCellStyle' => $this->themeTableHeaderCellStyle(),
            'pdfVariant' => $this->layoutVariantKey(),
            'terminosLista' => $terminosLista,
            'validacionesLista' => $validacionesLista,
            'observacionesLista' => $observacionesLista,
            'anexosLista' => $anexosLista,
            'tituloAnexos' => $tituloAnexos,
            'documentacionLista' => $documentacionLista,
            'haySeccionAnexos' => $haySeccionAnexos,
            'haySeccionDocumentacion' => $haySeccionDocumentacion,
            'mostrarAtentamente' => PresupuestoPdf::debeMostrarBloqueAtentamenteDesdePayload($presupuestoPayload),
            'paginasAnexosPdf' => $paginasAnexosPdf,
            'paginasDocumentacionPdf' => $paginasDocumentacionPdf,
            'paginasTrasSeccionPresupuesto' => $paginasAnexosPdf + $paginasDocumentacionPdf,
            'cierreAtentamente' => PresupuestoPdfLayout::calcularCierreAtentamente($presupuestoPayload, $this),
            'conceptosListaPdf' => $presupuestoPayload['conceptos'] ?? [],
            'tieneBloqueTerminos' => count($terminosLista) > 0
                || count($validacionesLista) > 0
                || count($observacionesLista) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dompdfOptions(): array
    {
        return [
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'margin-bottom' => self::DOMPDF_MARGIN_BOTTOM_PT,
            'enable-local-file-access' => false,
            'chroot' => public_path(),
            'compress' => true,
            'dpi' => self::DOMPDF_DPI,
        ];
    }
}
