<?php

namespace App\Support;

use App\Models\SolicitudCotizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class CotizacionPdf
{
    public static function renderPdfBinary(SolicitudCotizacion $solicitud): string
    {
        $solicitud->loadMissing(['proveedor', 'detalles']);

        $pdf = Pdf::loadView('cotizaciones.pdf', self::viewData($solicitud))
            ->setPaper('letter');

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

        return [
            'solicitud' => $solicitud,
            'proveedor' => $proveedor,
            'empresaNombre' => $empresa,
            'logoEmpresa' => self::logoEmpresaDataUri($proveedor?->logo),
            'logoNexprov' => self::logoNexprovDataUri(),
            'detalles' => $solicitud->detalles,
            'total' => (float) $solicitud->total,
            'fecha' => optional($solicitud->updated_at ?? $solicitud->created_at)->format('d/m/Y'),
        ];
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
