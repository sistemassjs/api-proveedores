<?php

namespace App\Support;

use App\Models\Presupuesto;
use App\Models\PresupuestoConcepto;
use App\Models\Proveedor;
use App\Services\Presupuesto\PresupuestoThemeService;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generación de PDF de presupuestos (DomPDF).
 */
final class PresupuestoPdf
{
    public static function mostrarSubencabezadoCompactoPdf(): bool
    {
        return PresupuestoPdfDocumentConfig::defaults()->mostrarSubencabezadoCompacto();
    }

    /**
     * Genera y retorna la respuesta PDF de un presupuesto.
     */
    public static function generarPdf(Presupuesto $presupuesto): Response
    {
        $binary = self::renderPdfBinary($presupuesto);
        $filename = "Presupuesto_{$presupuesto->numero_presupuesto}.pdf";

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Contenido binario del PDF (adjuntos en correo, etc.).
     */
    public static function renderPdfBinary(Presupuesto $presupuesto): string
    {
        $main = self::buildPdf($presupuesto)->output();

        return PresupuestoPdfAnexoMerger::unirSiHayAnexos($presupuesto, $main);
    }

    private static function buildPdf(Presupuesto $presupuesto)
    {
        $presupuesto->load(Presupuesto::eagerLodable());
        $presupuesto->asegurarTokenPublico();

        $logoProveedorBase64 = self::convertirLogoProveedorABase64($presupuesto->proveedor);
        $logosBase64 = self::convertirLogosABase64();
        $anexosBase64 = self::normalizarAnexosParaPdf($presupuesto);
        $gdDisponible = extension_loaded('gd');

        $qrCode = self::generarQrCodeParaPresupuesto($presupuesto);
        $qrUrl = $qrCode && $presupuesto->token_publico
            ? rtrim(config('app.frontend_url', config('app.url')), '/') . '/public/presupuesto/' . $presupuesto->token_publico
            : null;

        $proveedor = $presupuesto->proveedor;
        $df = $proveedor?->direccion_fiscal;
        $estado = \Illuminate\Support\Arr::get((array) ($df ?? []), 'estado', $proveedor->estado ?? 'México');
        $lugar = $proveedor?->ciudad ? ($proveedor->ciudad . ', ' . $estado) : null;

        $empDoc = $presupuesto->empresaReceptoraParaDocumento();

        $enunciadosClasificados = $presupuesto->getEnunciadosClasificados();

        $datosPresupuesto = [
            'proveedor' => $proveedor,
            'logo_proveedor_base64' => $logoProveedorBase64,
            'logos_base64' => $logosBase64,
            'gd_disponible' => $gdDisponible,
            'numero_presupuesto' => $presupuesto->numero_presupuesto,
            'uuid' => $presupuesto->uuid ?? null,
            'clave_unica' => $presupuesto->id,
            'fecha_emision' => $presupuesto->fecha_emision,
            'lugar' => $lugar,
            'concepto_general' => $presupuesto->concepto_general,
            'nombre_presupuesto' => $presupuesto->nombre_presupuesto,
            'titulo_anexos' => trim((string) ($presupuesto->titulo_anexos ?? '')) !== ''
                ? trim((string) $presupuesto->titulo_anexos)
                : 'Anexos',
            'titulo_anexos_pdf' => trim((string) ($presupuesto->titulo_anexos_pdf ?? '')) !== ''
                ? trim((string) $presupuesto->titulo_anexos_pdf)
                : 'Anexos PDF',
            'con_iva' => $presupuesto->con_iva,
            'iva_porcentaje' => $presupuesto->iva_porcentaje,
            'term_cond_moneda' => $presupuesto->term_cond_moneda ?? 'MXN',
            'subtotal' => $presupuesto->subtotal,
            'porcentaje_descuento' => $presupuesto->porcentaje_descuento,
            'cantidad_descuento' => $presupuesto->cantidad_descuento,
            'iva_total' => $presupuesto->iva_total,
            'total' => $presupuesto->total,
            'config_mostrar_totales' => (bool) ($presupuesto->config_mostrar_totales ?? true),
            'ppto_config' => is_array($presupuesto->ppto_config) ? $presupuesto->ppto_config : [],
            'config_emisor_presupuesto_id' => $presupuesto->config_emisor_presupuesto_id,
            'empresa_emisora_nombre' => $presupuesto->empresa_emisora_nombre,
            'empresa_emisora_puesto' => $presupuesto->empresa_emisora_puesto,
            'empresa_emisora_telefono' => $presupuesto->empresa_emisora_telefono,
            'empresa_emisora_correo' => $presupuesto->empresa_emisora_correo,
            'incluir_leyenda_atentamente' => (bool) ($presupuesto->incluir_leyenda_atentamente ?? true),
            'empresa_emisora_nombre_comercial' => $presupuesto->empresa_emisora_nombre_comercial,
            'empresa_receptora' => $empDoc,
            'receptor_lineas' => self::lineasDirigidoUnicas([
                'alias_empresa' => $empDoc['alias_empresa'],
                'nombre' => $empDoc['nombre'],
                'puesto' => $empDoc['puesto'],
                'empresa' => $empDoc['empresa'],
                'telefono' => $empDoc['telefono'],
                'correo' => $empDoc['correo'],
            ]),
            'conceptos' => $presupuesto->conceptos->map(function ($c) {
                $fila = [
                    'tipo' => $c->tipo ?? PresupuestoConcepto::TIPO_CONCEPTO,
                    'descripcion' => $c->descripcion,
                    'cantidad' => $c->cantidad,
                    'unidad' => $c->unidad,
                    'precio_unitario' => $c->precio_unitario,
                    'precio_total' => $c->precio_total,
                    'imagen_base64' => self::convertirArchivoAnexoABase64($c->imagen_path),
                    'proveedor_nombre' => $c->proveedor_nombre,
                    'proveedor_logo_url' => $c->proveedor_logo_url,
                ];
                if ($c->esParrafo()) {
                    $fila['descripcion'] = PresupuestoParrafoPdf::sanitizarTexto((string) $c->descripcion);
                }

                return $fila;
            })->toArray(),
            'anexos' => $anexosBase64,
            'documentacion_adjuntos' => [],
            'terminos_enunciados' => $enunciadosClasificados['terminos'],
            'validaciones_enunciados' => $enunciadosClasificados['validaciones'],
            'observaciones_enunciados' => $enunciadosClasificados['observaciones'],
            'qr_code' => $qrCode,
            'qr_url' => $qrUrl,
            'pdf_theme' => $presupuesto->pdf_theme,
        ];

        $pdfDocument = PresupuestoPdfDocumentConfig::fromPresupuestoPayload($datosPresupuesto);

        $pdfBuilder = Pdf::loadView($pdfDocument->viewName(), [
            'presupuesto' => $datosPresupuesto,
            'pdf' => $pdfDocument,
        ])
            ->setPaper('letter', 'portrait');

        foreach ($pdfDocument->dompdfOptions() as $option => $value) {
            $pdfBuilder->setOption($option, $value);
        }

        return $pdfBuilder;
    }

    private static function generarQrCodeParaPresupuesto(Presupuesto $presupuesto): ?string
    {
        $presupuesto->asegurarTokenPublico();
        $token = $presupuesto->token_publico;
        if (! $token) {
            return null;
        }

        $appUrl = config('app.frontend_url', config('app.url'));
        $urlWeb = rtrim($appUrl, '/') . '/public/presupuesto/' . $token;

        try {
            $renderer = new GDLibRenderer(200);
            $writer = new Writer($renderer);
            $qrPng = $writer->writeString($urlWeb);

            if ($qrPng !== '') {
                return 'data:image/png;base64,' . base64_encode($qrPng);
            }
        } catch (\Throwable $e) {
            Log::warning('Error al generar QR para presupuesto', [
                'presupuesto_id' => $presupuesto->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Logo GestiónPlus del pie del PDF de presupuestos (no se usa en correos).
     */
    public static function rutaLogoGestionPlusPresupuestoPdf(): ?string
    {
        foreach ([
            public_path('assets/logos/logo-gestionplus_only_blade.png'),
            public_path('assets/logos/logo-gestionplus_only_blade.webp'),
        ] as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function convertirLogosABase64(): array
    {
        $logos = ['facturapro' => '', 'constucc' => '', 'gestionplus' => ''];

        if (! extension_loaded('gd')) {
            return $logos;
        }

        $paths = [
            'facturapro' => public_path('assets/logos/logo-facturapro.png'),
            'constucc' => public_path('assets/logos/logo-construcc.png'),
            'gestionplus' => self::rutaLogoGestionPlusPresupuestoPdf(),
        ];

        foreach ($paths as $key => $path) {
            $dataUri = self::archivoImagenLocalADataUri($path);
            if ($dataUri !== '') {
                $logos[$key] = $dataUri;
            }
        }

        return $logos;
    }

    private static function archivoImagenLocalADataUri(?string $absolutePath): string
    {
        if ($absolutePath === null || $absolutePath === '' || ! is_readable($absolutePath)) {
            return '';
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'gif', 'webp'], true) && ! extension_loaded('gd')) {
            return '';
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false || $data === '') {
            return '';
        }

        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($data);
    }

    private static function convertirLogoProveedorABase64(?Proveedor $proveedor): string
    {
        if (! $proveedor || empty($proveedor->logo)) {
            return '';
        }

        try {
            $logoPath = null;
            if (filter_var($proveedor->logo, FILTER_VALIDATE_URL)) {
                return '';
            }
            if (str_starts_with($proveedor->logo, '/') || str_starts_with($proveedor->logo, 'storage/')) {
                $logoPath = public_path($proveedor->logo);
            } else {
                $logoPath = public_path('storage/' . $proveedor->logo);
            }
            if (! $logoPath || ! file_exists($logoPath) || ! is_readable($logoPath)) {
                return '';
            }
            $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'gif']) && ! extension_loaded('gd')) {
                return '';
            }
            $data = @file_get_contents($logoPath);
            if ($data === false || $data === '') {
                return '';
            }
            $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : ($ext === 'gif' ? 'image/gif' : 'image/png');

            return 'data:' . $mime . ';base64,' . base64_encode($data);
        } catch (\Throwable $e) {
            Log::warning('Error al convertir logo proveedor', ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizarAnexosParaPdf(Presupuesto $presupuesto): array
    {
        return $presupuesto->anexos
            ->sortBy([
                ['orden', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->map(function ($anexo) {
                $base64 = self::convertirArchivoAnexoABase64($anexo->archivo_path);

                return [
                    'id' => $anexo->id,
                    'orden' => (int) ($anexo->orden ?? 0),
                    'titulo' => (string) $anexo->titulo,
                    'descripcion' => $anexo->descripcion,
                    'precio' => $anexo->precio !== null ? (float) $anexo->precio : null,
                    'archivo_base64' => $base64,
                    'archivo_width' => $anexo->archivo_width !== null ? (int) $anexo->archivo_width : null,
                    'archivo_height' => $anexo->archivo_height !== null ? (int) $anexo->archivo_height : null,
                    'archivo_aspect_ratio' => $anexo->archivo_aspect_ratio !== null ? (float) $anexo->archivo_aspect_ratio : null,
                ];
            })
            ->toArray();
    }

    private static function convertirArchivoAnexoABase64(?string $archivoPath): string
    {
        if (! $archivoPath) {
            return '';
        }

        $path = trim($archivoPath);
        if (str_starts_with($path, 'data:image/')) {
            if (! preg_match('/^data:image\/[^;]+;base64,(.+)$/i', $path, $m)) {
                return '';
            }
            $decoded = base64_decode($m[1], true);

            return $decoded !== false
                ? PresupuestoAnexoImagenOptimizer::dataUriParaPdf($decoded)
                : '';
        }

        if (! Storage::disk('public')->exists($path)) {
            return '';
        }

        $binary = Storage::disk('public')->get($path);

        return PresupuestoAnexoImagenOptimizer::dataUriParaPdf($binary);
    }

    /**
     * Anexos listos para la plantilla Blade del PDF.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function anexosParaPlantillaPdf(Presupuesto $presupuesto): array
    {
        return self::normalizarAnexosParaPdf($presupuesto);
    }

    public static function formatMontoLegal(float|int|string|null $value, ?string $currency = 'MXN'): string
    {
        return self::formatMontoLetraLegal($value, $currency);
    }

    public static function formatMontoNumeroLegal(float|int|string|null $value, ?string $currency = 'MXN'): string
    {
        $amount = round((float) ($value ?? 0), 2);
        $codigo = self::normalizarCodigoMoneda($currency);
        $formatted = number_format($amount, 2, '.', ',');

        return match ($codigo) {
            'USD' => "USD {$formatted}",
            'EUR' => "EUR {$formatted}",
            default => '$' . $formatted . ' MXN',
        };
    }

    public static function formatMontoLetraLegal(float|int|string|null $value, ?string $currency = 'MXN'): string
    {
        $amount = round((float) ($value ?? 0), 2);
        $codigo = self::normalizarCodigoMoneda($currency);
        [$enteroStr, $decimales] = explode('.', number_format($amount, 2, '.', ''));

        $entero = abs((int) $enteroStr);
        $texto = self::numeroALetras($entero, true);

        if ((int) $enteroStr < 0) {
            $texto = 'menos ' . $texto;
        }

        [$singular, $plural, $sufijo] = self::currencyNames($codigo);
        $monedaTexto = $entero === 1 ? $singular : $plural;

        $linea = trim($texto . ' ' . $monedaTexto . ' ' . $decimales . '/100 ' . $sufijo);

        return '(' . self::mayusculasEspanol($linea) . ')';
    }

    private static function normalizarCodigoMoneda(?string $currency): string
    {
        $codigo = strtoupper(trim((string) ($currency ?? 'MXN')));

        return in_array($codigo, ['MXN', 'USD', 'EUR'], true)
            ? $codigo
            : 'MXN';
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function currencyNames(string $codigo): array
    {
        return match ($codigo) {
            'USD' => ['DÓLAR ESTADOUNIDENSE', 'DÓLARES ESTADOUNIDENSES', 'USD'],
            'EUR' => ['EURO', 'EUROS', 'EUR'],
            default => ['PESO', 'PESOS', 'M.N.'],
        };
    }

    private static function mayusculasEspanol(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }

        return strtoupper($value);
    }

    private static function numeroALetras(int $numero, bool $apocope = true): string
    {
        if ($numero === 0) {
            return 'cero';
        }

        if ($numero < 0) {
            return 'menos ' . self::numeroALetras(abs($numero), $apocope);
        }

        if ($numero < 1000) {
            return self::centenasALetras($numero, $apocope);
        }

        if ($numero < 1000000) {
            $miles = intdiv($numero, 1000);
            $resto = $numero % 1000;
            $texto = $miles === 1
                ? 'mil'
                : self::numeroALetras($miles, true) . ' mil';

            return trim($texto . ' ' . ($resto > 0 ? self::numeroALetras($resto, $apocope) : ''));
        }

        if ($numero < 1000000000) {
            $millones = intdiv($numero, 1000000);
            $resto = $numero % 1000000;
            $texto = $millones === 1
                ? 'un millón'
                : self::numeroALetras($millones, true) . ' millones';

            return trim($texto . ' ' . ($resto > 0 ? self::numeroALetras($resto, $apocope) : ''));
        }

        $milesMillones = intdiv($numero, 1000000000);
        $resto = $numero % 1000000000;
        $texto = $milesMillones === 1
            ? 'mil millones'
            : self::numeroALetras($milesMillones, true) . ' mil millones';

        return trim($texto . ' ' . ($resto > 0 ? self::numeroALetras($resto, $apocope) : ''));
    }

    private static function centenasALetras(int $numero, bool $apocope): string
    {
        $basicos = [
            0 => '',
            1 => $apocope ? 'un' : 'uno',
            2 => 'dos',
            3 => 'tres',
            4 => 'cuatro',
            5 => 'cinco',
            6 => 'seis',
            7 => 'siete',
            8 => 'ocho',
            9 => 'nueve',
            10 => 'diez',
            11 => 'once',
            12 => 'doce',
            13 => 'trece',
            14 => 'catorce',
            15 => 'quince',
            16 => 'dieciséis',
            17 => 'diecisiete',
            18 => 'dieciocho',
            19 => 'diecinueve',
            20 => 'veinte',
            21 => $apocope ? 'veintiún' : 'veintiuno',
            22 => 'veintidós',
            23 => 'veintitrés',
            24 => 'veinticuatro',
            25 => 'veinticinco',
            26 => 'veintiséis',
            27 => 'veintisiete',
            28 => 'veintiocho',
            29 => 'veintinueve',
        ];

        if ($numero < 30) {
            return $basicos[$numero];
        }

        if ($numero < 100) {
            $decenas = [
                3 => 'treinta',
                4 => 'cuarenta',
                5 => 'cincuenta',
                6 => 'sesenta',
                7 => 'setenta',
                8 => 'ochenta',
                9 => 'noventa',
            ];
            $d = intdiv($numero, 10);
            $u = $numero % 10;

            if ($u === 0) {
                return $decenas[$d];
            }

            $unidad = $u === 1
                ? ($apocope ? 'un' : 'uno')
                : $basicos[$u];

            return $decenas[$d] . ' y ' . $unidad;
        }

        if ($numero === 100) {
            return 'cien';
        }

        $centenas = [
            1 => 'ciento',
            2 => 'doscientos',
            3 => 'trescientos',
            4 => 'cuatrocientos',
            5 => 'quinientos',
            6 => 'seiscientos',
            7 => 'setecientos',
            8 => 'ochocientos',
            9 => 'novecientos',
        ];

        $c = intdiv($numero, 100);
        $resto = $numero % 100;

        return trim($centenas[$c] . ' ' . ($resto > 0 ? self::centenasALetras($resto, $apocope) : ''));
    }

    /**
     * Nombre de persona en documento (mayúsculas).
     */
    public static function formatoNombrePersonaDocumento(?string $text): ?string
    {
        $t = trim((string) ($text ?? ''));

        return $t === '' ? null : mb_strtoupper($t, 'UTF-8');
    }

    /**
     * Puesto o razón social / empresa en documento (mayúsculas).
     */
    public static function formatoPuestoEmpresaDocumento(?string $text): ?string
    {
        return self::formatoNombrePersonaDocumento($text);
    }

    /**
     * Líneas para «Dirigido a:» en el PDF (misma vista que el preview: nombre → puesto → empresa).
     *
     * @param  array{
     *   alias_empresa?: string|null,
     *   nombre?: string|null,
     *   puesto?: string|null,
     *   empresa?: string|null,
     *   telefono?: string|null,
     *   correo?: string|null
     * }  $r  Solo se usan nombre, puesto y empresa; el resto se ignora.
     * @return list<string>
     */
    public static function lineasDirigidoUnicas(array $r): array
    {
        $ordenados = [
            self::formatoNombrePersonaDocumento($r['nombre'] ?? null),
            self::formatoPuestoEmpresaDocumento($r['puesto'] ?? null),
            self::formatoPuestoEmpresaDocumento($r['empresa'] ?? null),
        ];

        $lines = [];
        foreach ($ordenados as $v) {
            if ($v !== null && $v !== '') {
                $lines[] = $v;
            }
        }

        return $lines;
    }

    /**
     * Misma lógica que {@see lineasDirigidoUnicas} leyendo solo las columnas `empresa_receptora_*` del presupuesto guardado.
     */
    public static function lineasReceptorPdfDesdeColumnasPresupuesto(Presupuesto $p): array
    {
        return self::lineasDirigidoUnicas([
            'alias_empresa' => $p->empresa_receptora_alias,
            'nombre' => $p->empresa_receptora_nombre,
            'puesto' => $p->empresa_receptora_puesto,
            'empresa' => $p->empresa_receptora_empresa,
            'telefono' => $p->empresa_receptora_telefono,
            'correo' => $p->empresa_receptora_correo,
        ]);
    }

    /**
     * Misma secuencia que {@see lineasReceptorPdfDesdeColumnasPresupuesto} desde un arreglo validado/normalizado
     * (claves `empresa_receptora_*`, p. ej. preview PDF desde formulario).
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function lineasReceptorPdfDesdePayloadReceptor(array $payload): array
    {
        return self::lineasDirigidoUnicas([
            'alias_empresa' => $payload['empresa_receptora_alias'] ?? null,
            'nombre' => $payload['empresa_receptora_nombre'] ?? null,
            'puesto' => $payload['empresa_receptora_puesto'] ?? null,
            'empresa' => $payload['empresa_receptora_empresa'] ?? null,
            'telefono' => $payload['empresa_receptora_telefono'] ?? null,
            'correo' => $payload['empresa_receptora_correo'] ?? null,
        ]);
    }

    /**
     * Líneas de contacto emisor-persona desde columnas snapshot del presupuesto.
     *
     * @return list<string>
     * @deprecated Usar bloque Atentamente ({@see debeMostrarBloqueAtentamenteDesdePayload}).
     */
    public static function lineasEmisorContactoPdf(Presupuesto $p): array
    {
        return self::lineasEmisorContactoPdfDesdePayload([
            'empresa_emisora_nombre' => $p->empresa_emisora_nombre,
            'empresa_emisora_puesto' => $p->empresa_emisora_puesto,
            'empresa_emisora_telefono' => $p->empresa_emisora_telefono,
            'empresa_emisora_correo' => $p->empresa_emisora_correo,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function debeMostrarBloqueAtentamenteDesdePayload(array $payload): bool
    {
        $id = $payload['config_emisor_presupuesto_id'] ?? null;
        if ($id === null || $id === '' || (int) $id <= 0) {
            return false;
        }

        if (array_key_exists('incluir_leyenda_atentamente', $payload)) {
            return (bool) $payload['incluir_leyenda_atentamente'];
        }

        return true;
    }

    /**
     * Datos del cierre «Atentamente» (solo líneas con valor).
     *
     * @param  array<string, mixed>  $payload
     * @return array{nombre: ?string, puesto: ?string, empresa: ?string, telefono: ?string, correo: ?string}
     */
    public static function datosBloqueAtentamenteDesdePayload(array $payload): array
    {
        $trim = static function (?string $value): ?string {
            $text = trim((string) ($value ?? ''));

            return $text !== '' ? $text : null;
        };

        return [
            'nombre' => $trim($payload['empresa_emisora_nombre'] ?? null),
            'puesto' => $trim($payload['empresa_emisora_puesto'] ?? null),
            'empresa' => $trim($payload['empresa_emisora_nombre_comercial'] ?? null),
            'telefono' => $trim($payload['empresa_emisora_telefono'] ?? null),
            'correo' => $trim($payload['empresa_emisora_correo'] ?? null),
        ];
    }

    /**
     * Líneas para dibujar «Atentamente» en el pie de la última página del PDF (DomPDF page_script).
     * Misma jerarquía que «Dirigido a:» (título / nombre / líneas secundarias).
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{text: string, role: 'title'|'name'|'info'}>
     */
    public static function lineasAtentamentePieUltimaPaginaDesdePayload(array $payload): array
    {
        if (! self::debeMostrarBloqueAtentamenteDesdePayload($payload)) {
            return [];
        }

        $datos = self::datosBloqueAtentamenteDesdePayload($payload);

        $lineas = [
            ['text' => 'Atentamente:', 'role' => 'title'],
        ];

        $nombre = self::formatoNombrePersonaDocumento($datos['nombre'] ?? null);
        if ($nombre !== null) {
            $lineas[] = ['text' => $nombre, 'role' => 'name'];
        }

        $puesto = self::formatoPuestoEmpresaDocumento($datos['puesto'] ?? null);
        if ($puesto !== null) {
            $lineas[] = ['text' => $puesto, 'role' => 'info'];
        }

        $empresa = self::formatoPuestoEmpresaDocumento($datos['empresa'] ?? null);
        if ($empresa !== null) {
            $lineas[] = ['text' => $empresa, 'role' => 'info'];
        }

        $telefono = trim((string) ($datos['telefono'] ?? ''));
        if ($telefono !== '') {
            $lineas[] = ['text' => 'Tel. '.$telefono, 'role' => 'info'];
        }

        $correo = trim((string) ($datos['correo'] ?? ''));
        if ($correo !== '') {
            $lineas[] = ['text' => $correo, 'role' => 'info'];
        }

        return $lineas;
    }

    /**
     * RGB normalizado 0–1 para DomPDF a partir de hex (#3498db o 3498db).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public static function hexColorToPdfRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [0.204, 0.596, 0.859];
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /**
     * Estilos por rol (pt, interlineado) alineados con «Dirigido a:» (.receptor-* / .tw-card-title + .tw-receptor-*).
     *
     * @param  array<string, string|float>|null  $themeVariables  Variables del tema PDF (plantilla tailwind).
     * @return array<string, array{size: float, bold: bool, color: array{0: float, 1: float, 2: float}, lh: float}>
     */
    public static function estilosAtentamentePiePorRol(string $variant = 'tailwind', ?array $themeVariables = null): array
    {
        if ($variant === 'default') {
            return [
                'title' => ['size' => 6.5, 'bold' => true, 'color' => self::hexColorToPdfRgb('#3498db'), 'lh' => 10.0],
                'name' => ['size' => 9.0, 'bold' => true, 'color' => self::hexColorToPdfRgb('#2c3e50'), 'lh' => 11.0],
                'info' => ['size' => 7.0, 'bold' => false, 'color' => self::hexColorToPdfRgb('#5f6f89'), 'lh' => 9.0],
            ];
        }

        $vars = $themeVariables ?? [];
        $primary = (string) ($vars['color-primary'] ?? '#2563eb');
        $infoLine = (string) ($vars['color-receptor-line'] ?? '#475569');

        return [
            'title' => ['size' => 6.0, 'bold' => true, 'color' => self::hexColorToPdfRgb($primary), 'lh' => 9.5],
            'name' => ['size' => 9.0, 'bold' => true, 'color' => self::hexColorToPdfRgb('#111827'), 'lh' => 11.0],
            'info' => ['size' => 7.0, 'bold' => false, 'color' => self::hexColorToPdfRgb($infoLine), 'lh' => 9.0],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function lineasEmisorContactoPdfDesdePayload(array $payload): array
    {
        $lines = [];
        $nombre = trim((string) ($payload['empresa_emisora_nombre'] ?? ''));
        if ($nombre !== '') {
            $lines[] = $nombre;
        }
        $puesto = trim((string) ($payload['empresa_emisora_puesto'] ?? ''));
        if ($puesto !== '') {
            $lines[] = $puesto;
        }
        $tel = trim((string) ($payload['empresa_emisora_telefono'] ?? ''));
        if ($tel !== '') {
            $lines[] = 'Tel. '.$tel;
        }
        $correo = trim((string) ($payload['empresa_emisora_correo'] ?? ''));
        if ($correo !== '') {
            $lines[] = $correo;
        }

        return $lines;
    }

    /**
     * Datos para subencabezado compacto (páginas 2+ de la sección presupuesto).
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     nombre: string,
     *     rfc: ?string,
     *     contacto: ?string,
     *     folio: string,
     *     fecha: string
     * }
     */
    public static function datosSubencabezadoCompactoDesdePayload(array $payload): array
    {
        $p = $payload['proveedor'] ?? null;
        $emisorComercial = trim((string) ($p->nombre_comercial ?? ''));
        $emisorRazonSocial = trim((string) ($p->razon_social ?? ''));
        $nombre = $emisorComercial !== ''
            ? $emisorComercial
            : ($emisorRazonSocial !== '' ? $emisorRazonSocial : 'Empresa Proveedora');

        $fecha = $payload['fecha_emision'] ?? now();
        if (is_string($fecha)) {
            $fecha = \Carbon\Carbon::parse($fecha);
        }
        $fechaFormateada = $fecha->locale('es')->translatedFormat('d \d\e F \d\e\l Y');

        return [
            'nombre' => mb_strtoupper($nombre, 'UTF-8'),
            'rfc' => trim((string) ($p->rfc ?? '')) ?: null,
            // Cabecera/subencabezado: solo perfil empresa; contacto de tarjeta va en Atentamente.
            'contacto' => null,
            'folio' => (string) ($payload['numero_presupuesto'] ?? 'PRES-000001'),
            'fecha' => $fechaFormateada,
        ];
    }

    /**
     * Ruta temporal de logo para DomPDF page_script (reutiliza el mismo archivo por hash).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function prepararLogoParaPageScript(array $payload): ?string
    {
        $raw = $payload['logo_proveedor_base64'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);
        $mime = 'png';
        if (str_starts_with($raw, 'data:image')) {
            if (preg_match('#^data:image/(\w+);base64,#i', $raw, $m)) {
                $mime = strtolower($m[1]);
                if ($mime === 'jpeg') {
                    $mime = 'jpg';
                }
            }
            $parts = explode(',', $raw, 2);
            $raw = $parts[1] ?? '';
        }

        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $ext = in_array($mime, ['png', 'jpg', 'gif', 'webp'], true) ? $mime : 'png';
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'presupuesto-pdf-logo-'.md5($binary).'.'.$ext;
        if (! is_file($path)) {
            file_put_contents($path, $binary);
        }

        return str_replace('\\', '/', $path);
    }

    /**
     * Ancho y alto en mm del logo dentro de un recuadro máximo (object-fit: contain).
     *
     * @return array{width_mm: float, height_mm: float}
     */
    public static function dimensionesLogoEnRecuadroMm(
        float $imageWidthPx,
        float $imageHeightPx,
        float $maxWidthMm,
        float $maxHeightMm,
    ): array {
        if ($imageWidthPx <= 0 || $imageHeightPx <= 0) {
            return [
                'width_mm' => $maxWidthMm,
                'height_mm' => $maxHeightMm,
            ];
        }

        if ($imageWidthPx >= $imageHeightPx) {
            $widthMm = $maxWidthMm;
            $heightMm = $widthMm * ($imageHeightPx / $imageWidthPx);
        } else {
            $heightMm = $maxHeightMm;
            $widthMm = $heightMm * ($imageWidthPx / $imageHeightPx);
        }

        $scaleDown = min(
            1.0,
            $maxWidthMm / max($widthMm, 0.0001),
            $maxHeightMm / max($heightMm, 0.0001),
        );
        $widthMm *= $scaleDown;
        $heightMm *= $scaleDown;

        return [
            'width_mm' => max(0.01, min($maxWidthMm, $widthMm)),
            'height_mm' => max(0.01, min($maxHeightMm, $heightMm)),
        ];
    }

    /**
     * Dimensiones del logo de subencabezado (páginas 2+) a partir del archivo temporal.
     *
     * @return array{width_mm: float, height_mm: float}|null
     */
    public static function dimensionesLogoSubencabezadoDesdeRuta(
        ?string $logoImagePath,
        float $maxWidthMm = 26.0,
        float $maxHeightMm = 15.0,
    ): ?array {
        if ($logoImagePath === null || $logoImagePath === '' || ! is_file($logoImagePath)) {
            return null;
        }

        $info = @getimagesize($logoImagePath);
        if (! is_array($info) || empty($info[0]) || empty($info[1])) {
            return null;
        }

        return self::dimensionesLogoEnRecuadroMm(
            (float) $info[0],
            (float) $info[1],
            $maxWidthMm,
            $maxHeightMm,
        );
    }

    /**
     * Variables del tema visual del presupuesto (estampado PDF, subencabezado, etc.).
     *
     * @return array<string, string|float>
     */
    public static function variablesTemaResueltas(?string $pdfThemeKey): array
    {
        $service = app(PresupuestoThemeService::class);
        $key = $service->resolveThemeKey($pdfThemeKey);

        return $service->getTheme($key)['variables'];
    }

    /**
     * Colores de estampado en RGB 0–1 (DomPDF).
     *
     * @return array{
     *     primary: array{0: float, 1: float, 2: float},
     *     heading: array{0: float, 1: float, 2: float},
     *     muted: array{0: float, 1: float, 2: float}
     * }
     */
    public static function coloresEstampadoPdfRgb(?string $pdfThemeKey): array
    {
        $variables = self::variablesTemaResueltas($pdfThemeKey);

        return [
            'primary' => self::hexColorToPdfRgb((string) ($variables['color-primary'] ?? '#2563eb')),
            'heading' => self::hexColorToPdfRgb((string) ($variables['color-heading'] ?? '#1e293b')),
            'muted' => self::hexColorToPdfRgb((string) ($variables['color-slate-600'] ?? '#475569')),
        ];
    }

    /**
     * Paleta de estampado FPDI (RGB 0–255).
     *
     * @return array{
     *     primary: array{0: int, 1: int, 2: int},
     *     heading: array{0: int, 1: int, 2: int},
     *     border: array{0: int, 1: int, 2: int}
     * }
     */
    public static function paletteEstampadoFpdiRgb255(?string $pdfThemeKey): array
    {
        $variables = self::variablesTemaResueltas($pdfThemeKey);

        return [
            'primary' => self::hexToRgb255((string) ($variables['color-primary'] ?? '#2563eb')),
            'heading' => self::hexToRgb255((string) ($variables['color-heading'] ?? '#1e293b')),
            'border' => self::hexToRgb255((string) ($variables['color-slate-200'] ?? '#e2e8f0')),
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function hexToRgb255(string $hex): array
    {
        $normalized = self::hexColorToPdfRgb($hex);

        return [
            (int) round($normalized[0] * 255),
            (int) round($normalized[1] * 255),
            (int) round($normalized[2] * 255),
        ];
    }

    /**
     * DomPDF page_script: subencabezado compacto (págs. 2+). No se registra si {@see mostrarSubencabezadoCompactoPdf()} es false.
     */
    public static function generarPageScriptSubencabezadoPresupuesto(
        float $margenMm,
        int $paginasTrasSeccionPresupuesto,
        array $payload,
        string $variant = 'tailwind',
        bool $saltoPaginaAntesAtentamente = false,
        ?string $logoImagePath = null,
    ): string {
        if (! self::mostrarSubencabezadoCompactoPdf()) {
            return '';
        }

        unset($paginasTrasSeccionPresupuesto, $saltoPaginaAntesAtentamente, $variant);

        $datos = self::datosSubencabezadoCompactoDesdePayload($payload);
        $mmToPt = static fn (float $mm): int => (int) round($mm * 2.834645669);
        $x = $mmToPt($margenMm);
        $pdfTheme = isset($payload['pdf_theme']) ? (string) $payload['pdf_theme'] : null;
        $estampado = self::coloresEstampadoPdfRgb($pdfTheme !== '' ? $pdfTheme : null);
        $primary = $estampado['primary'];
        $textDark = $estampado['heading'];
        $textMuted = $estampado['muted'];

        $nombreEsc = addcslashes($datos['nombre'], "\\'");
        $folioEsc = addcslashes($datos['folio'], "\\'");
        $fechaEsc = addcslashes($datos['fecha'], "\\'");
        $rfcEsc = $datos['rfc'] !== null ? addcslashes($datos['rfc'], "\\'") : '';
        $contactoEsc = $datos['contacto'] !== null ? addcslashes($datos['contacto'], "\\'") : '';
        $logoPathEsc = $logoImagePath !== null && $logoImagePath !== ''
            ? addcslashes(str_replace('\\', '/', $logoImagePath), "\\'")
            : '';

        $r = $primary[0];
        $g = $primary[1];
        $b = $primary[2];
        $dr = $textDark[0];
        $dg = $textDark[1];
        $db = $textDark[2];
        $mr = $textMuted[0];
        $mg = $textMuted[1];
        $mb = $textMuted[2];

        $logoMaxWMm = 26.0;
        $logoMaxHMm = 15.0;
        $logoDims = self::dimensionesLogoSubencabezadoDesdeRuta($logoImagePath, $logoMaxWMm, $logoMaxHMm);
        $logoDrawWMm = $logoDims['width_mm'] ?? $logoMaxWMm;
        $logoDrawHMm = $logoDims['height_mm'] ?? $logoMaxHMm;
        $logoDrawWPt = $mmToPt($logoDrawWMm);
        $logoDrawHPt = $mmToPt($logoDrawHMm);
        $logoBoxMaxHPt = $mmToPt($logoMaxHMm);
        $logoOffsetYPt = (int) max(0, (int) round(($logoBoxMaxHPt - $logoDrawHPt) / 2));
        $logoGapPt = $mmToPt(2.5);
        $bandTopPt = $mmToPt(10.0);
        $bandHeightPt = $mmToPt(24.0);

        return <<<SCRIPT
if (\$PAGE_NUM <= 1) {
    return;
}
\$fontBold = \$fontMetrics->getFont('DejaVu Sans', 'bold');
\$fontNorm = \$fontMetrics->getFont('DejaVu Sans', 'normal');
\$pageWidth = \$pdf->get_width();
\$x = {$x};
\$y = {$bandTopPt};
\$pdf->filled_rectangle(0, {$bandTopPt} - 4, \$pageWidth, {$bandHeightPt}, array(1, 1, 1));
\$textX = \$x;
if ('{$logoPathEsc}' !== '') {
    \$logoW = {$logoDrawWPt};
    \$logoH = {$logoDrawHPt};
    \$logoY = {$bandTopPt} + {$logoOffsetYPt};
    \$pdf->image('{$logoPathEsc}', \$x, \$logoY, \$logoW, \$logoH);
    \$textX = \$x + \$logoW + {$logoGapPt};
}
\$pdf->text(\$textX, \$y + 2, '{$nombreEsc}', \$fontBold, 7.5, array({$dr}, {$dg}, {$db}));
\$folioWidth = \$fontMetrics->getTextWidth('{$folioEsc}', \$fontBold, 10);
\$folioX = \$pageWidth - {$x} - \$folioWidth;
\$pdf->text(\$folioX, \$y, 'PRESUPUESTO', \$fontNorm, 5.5, array({$r}, {$g}, {$b}));
\$pdf->text(\$folioX, \$y + 7, '{$folioEsc}', \$fontBold, 10, array({$r}, {$g}, {$b}));
\$yLine = \$y + 7;
\$y += 11;
SCRIPT
            .($datos['rfc'] !== null ? <<<SCRIPT

\$pdf->text(\$textX, \$y, '{$rfcEsc}', \$fontNorm, 6, array({$mr}, {$mg}, {$mb}));
\$y += 8;
SCRIPT
                : '')
            .($datos['contacto'] !== null ? <<<SCRIPT

\$pdf->text(\$textX, \$y, '{$contactoEsc}', \$fontNorm, 6, array({$mr}, {$mg}, {$mb}));
\$y += 8;
SCRIPT
                : '')
            .<<<SCRIPT

\$fechaWidth = \$fontMetrics->getTextWidth('{$fechaEsc}', \$fontNorm, 6);
\$pdf->text(\$pageWidth - {$x} - \$fechaWidth, \$yLine + 11, '{$fechaEsc}', \$fontNorm, 6, array({$mr}, {$mg}, {$mb}));
\$lineY = {$bandTopPt} + {$bandHeightPt} - 6;
\$pdf->line({$x}, \$lineY, \$pageWidth - {$x}, \$lineY, array({$r}, {$g}, {$b}), 1.2);
SCRIPT;
    }
}
