<?php

namespace App\Support;

use App\Models\SolicitudCotizacion;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class CotizacionPdf
{
    public static function renderPdfBinary(SolicitudCotizacion $solicitud): string
    {
        $solicitud->loadMissing(['proveedor', 'detalles']);

        $pdf = Pdf::loadView('cotizaciones.pdf', self::viewData($solicitud))
            ->setPaper('letter')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
                'margin-bottom' => 70,
            ]);

        return $pdf->output();
    }

    public static function generarYGuardar(SolicitudCotizacion $solicitud): string
    {
        $binary = self::renderPdfBinary($solicitud);
        $path = 'cotizaciones/respuestas/'.$solicitud->proveedor_id.'/'.$solicitud->folio.'_'.now()->format('YmdHis').'.pdf';
        Storage::disk('private')->put($path, $binary);

        return $path;
    }

    public static function descargar(SolicitudCotizacion $solicitud): Response
    {
        $binary = self::renderPdfBinary($solicitud);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Cotizacion_'.$solicitud->folio.'.pdf"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function viewData(SolicitudCotizacion $solicitud): array
    {
        $proveedor = $solicitud->proveedor;
        $empresa = trim((string) ($proveedor?->nombre_comercial ?: $proveedor?->razon_social ?: 'Empresa'));
        $razonSocial = trim((string) ($proveedor?->razon_social ?: ''));
        $fechaBase = $solicitud->updated_at ?? $solicitud->created_at ?? now();
        $vigenciaHasta = $fechaBase->copy()->addDays(15);

        $telefono = trim(implode(' ', array_filter([
            $proveedor?->telefono_codigo_pais ? '+'.ltrim((string) $proveedor->telefono_codigo_pais, '+') : null,
            $proveedor?->telefono,
        ])));
        if ($telefono === '' && ! empty($proveedor?->contacto_telefono)) {
            $telefono = trim((string) $proveedor->contacto_telefono);
        }

        $direccion = trim((string) ($proveedor?->direccion_empresa ?: $proveedor?->direccion_fiscal ?: ''));
        $ciudad = trim((string) ($proveedor?->ciudad ?: ''));
        $estado = trim((string) ($proveedor?->estado ?: ''));
        $ciudadEstado = '';
        if ($ciudad !== '' || $estado !== '') {
            $ciudadEstado = $ciudad.($ciudad !== '' && $estado !== '' ? ', ' : '').$estado;
            if ($ciudadEstado !== '') {
                $ciudadEstado .= ', México';
            }
        }

        $total = (float) $solicitud->total;
        $fechaFormateada = $fechaBase->copy()->locale('es')->translatedFormat('d \d\e F \d\e\l Y');
        $codigoVerificacion = self::codigoVerificacion($solicitud);
        $payloadQr = self::payloadQr($solicitud, $codigoVerificacion, $total);

        $vigenciaFecha = $solicitud->vigencia_hasta
            ? $solicitud->vigencia_hasta->copy()
            : $vigenciaHasta;

        return [
            'solicitud' => $solicitud,
            'proveedor' => $proveedor,
            'empresaNombre' => $empresa,
            'empresaRazonSocial' => $razonSocial,
            'empresaRfc' => trim((string) ($proveedor?->rfc ?: '')),
            'empresaDireccion' => $direccion,
            'empresaCiudadEstado' => $ciudadEstado,
            'empresaTelefono' => $telefono,
            'empresaEmail' => trim((string) ($proveedor?->email ?: '')),
            'logoEmpresa' => self::logoEmpresaDataUri($proveedor?->logo),
            'logoNexprov' => self::logoNexprovDataUri(),
            'detalles' => $solicitud->detalles,
            'total' => $total,
            'fecha' => $fechaBase->format('d/m/Y'),
            'fechaFormateada' => $fechaFormateada,
            'fechaLarga' => self::fechaLarga($fechaBase),
            'vigencia' => self::fechaLarga($vigenciaFecha),
            'politicasLista' => $solicitud->politicasLista(),
            'importeConLetra' => PresupuestoPdf::formatMontoLegal($total, 'MXN'),
            'codigoVerificacion' => $codigoVerificacion,
            'qrCode' => self::generarQrDataUri($payloadQr, $solicitud->id),
            'qrPayload' => $payloadQr,
        ];
    }

    private static function codigoVerificacion(SolicitudCotizacion $solicitud): string
    {
        $raw = hash('sha256', implode('|', [
            $solicitud->id,
            $solicitud->folio,
            $solicitud->proveedor_id,
            (string) $solicitud->created_at,
        ]));

        return strtoupper(substr($raw, 0, 8).'-'.substr($raw, 8, 4).'-'.substr($raw, 12, 4).'-'.substr($raw, 16, 4).'-'.substr($raw, 20, 12));
    }

    private static function payloadQr(SolicitudCotizacion $solicitud, string $codigoVerificacion, float $total): string
    {
        return implode('|', [
            'NEXPROV-COT',
            (string) $solicitud->folio,
            (string) $solicitud->id,
            (string) $solicitud->proveedor_id,
            number_format($total, 2, '.', ''),
            $codigoVerificacion,
        ]);
    }

    private static function generarQrDataUri(string $payload, int $solicitudId): string
    {
        try {
            $renderer = new GDLibRenderer(180);
            $writer = new Writer($renderer);
            $png = $writer->writeString($payload);

            if ($png !== '') {
                return 'data:image/png;base64,'.base64_encode($png);
            }
        } catch (\Throwable $e) {
            Log::warning('CotizacionPdf: no se pudo generar QR', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
            ]);
        }

        return '';
    }

    private static function fechaLarga(\DateTimeInterface $fecha): string
    {
        $meses = [
            1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
            5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
            9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
        ];

        $dia = $fecha->format('d');
        $mes = $meses[(int) $fecha->format('n')] ?? $fecha->format('m');
        $anio = $fecha->format('Y');

        return "{$dia} / {$mes} / {$anio}";
    }

    private static function logoEmpresaDataUri(?string $logoPath): string
    {
        if (! $logoPath) {
            return '';
        }

        try {
            if (Storage::disk('public')->exists($logoPath)) {
                $absolute = Storage::disk('public')->path($logoPath);
                if (is_readable($absolute)) {
                    $mime = mime_content_type($absolute) ?: 'image/png';
                    $data = base64_encode((string) file_get_contents($absolute));

                    return "data:{$mime};base64,{$data}";
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    private static function logoNexprovDataUri(): string
    {
        foreach ([
            public_path('assets/logos/logo-nexprov.png'),
            public_path('assets/logos/logo-nexprov-transparent.png'),
        ] as $path) {
            if (is_readable($path)) {
                $mime = mime_content_type($path) ?: 'image/png';
                $data = base64_encode((string) file_get_contents($path));

                return "data:{$mime};base64,{$data}";
            }
        }

        return '';
    }
}
