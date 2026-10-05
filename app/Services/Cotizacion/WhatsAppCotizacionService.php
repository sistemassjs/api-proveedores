<?php

namespace App\Services\Cotizacion;

use App\Models\SolicitudCotizacion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp para respuestas de cotización.
 * Siempre genera deep-link wa.me. API opcional (no bloquea el flujo si falla).
 */
class WhatsAppCotizacionService
{
    /**
     * @return array{modo: string, estado: string, link: string, error: ?string}
     */
    public function enviarOGenerarLink(
        SolicitudCotizacion $solicitud,
        string $mensaje,
        ?string $pdfPublicUrl = null
    ): array {
        $telefono = $this->normalizarTelefono(
            $solicitud->cliente_whatsapp ?: $solicitud->cliente_telefono
        );

        $texto = $this->armarTexto($solicitud, $mensaje, $pdfPublicUrl);
        $link = $telefono
            ? 'https://wa.me/'.$telefono.'?text='.rawurlencode($texto)
            : '';

        $resultado = [
            'modo' => 'link',
            'estado' => $link !== '' ? 'link_generado' : 'error_api',
            'link' => $link,
            'error' => $link === '' ? 'Sin teléfono/WhatsApp del cliente para armar el enlace.' : null,
        ];

        if (! $this->apiHabilitada() || $telefono === '') {
            return $resultado;
        }

        try {
            $apiResult = $this->enviarViaApi($telefono, $texto);
            if ($apiResult['ok']) {
                return [
                    'modo' => 'api',
                    'estado' => 'enviado_api',
                    'link' => $link,
                    'error' => null,
                ];
            }

            $resultado['whatsapp_intento_api'] = true;
            $resultado['error'] = $apiResult['error'] ?? 'Error desconocido API WhatsApp';
            $resultado['estado'] = $link !== '' ? 'error_api' : 'error_api';
            // Mantener link como fallback usable
            if ($link !== '') {
                $resultado['estado'] = 'link_generado';
                $resultado['modo'] = 'link';
            }

            return $resultado;
        } catch (\Throwable $e) {
            Log::warning('WhatsAppCotizacionService: fallo API no bloqueante', [
                'solicitud_id' => $solicitud->id,
                'error' => $e->getMessage(),
            ]);

            $resultado['error'] = $e->getMessage();
            if ($link !== '') {
                $resultado['estado'] = 'link_generado';
                $resultado['modo'] = 'link';
            }

            return $resultado;
        }
    }

    private function apiHabilitada(): bool
    {
        return (bool) config('services.whatsapp.enabled', false)
            && filled(config('services.whatsapp.api_url'))
            && filled(config('services.whatsapp.token'));
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private function enviarViaApi(string $telefono, string $texto): array
    {
        $url = rtrim((string) config('services.whatsapp.api_url'), '/');
        $token = (string) config('services.whatsapp.token');
        $from = config('services.whatsapp.from');

        $payload = array_filter([
            'to' => $telefono,
            'type' => 'text',
            'text' => ['body' => $texto],
            'from' => $from,
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post($url, $payload);

        if ($response->successful()) {
            return ['ok' => true];
        }

        return [
            'ok' => false,
            'error' => 'HTTP '.$response->status().': '.$response->body(),
        ];
    }

    private function armarTexto(SolicitudCotizacion $solicitud, string $mensaje, ?string $pdfPublicUrl): string
    {
        $empresa = trim((string) (
            $solicitud->proveedor?->nombre_comercial
            ?: $solicitud->proveedor?->razon_social
            ?: 'la empresa'
        ));

        $lineas = [
            "Hola {$solicitud->cliente_nombre},",
            "",
            "Te enviamos la cotización {$solicitud->folio} de {$empresa}.",
        ];

        if (trim($mensaje) !== '') {
            $lineas[] = '';
            $lineas[] = trim($mensaje);
        }

        $lineas[] = '';
        $lineas[] = 'Total: $'.number_format((float) $solicitud->total, 2);

        if ($pdfPublicUrl) {
            $lineas[] = '';
            $lineas[] = 'PDF: '.$pdfPublicUrl;
        }

        $lineas[] = '';
        $lineas[] = '— Enviado con NexProv';

        return implode("\n", $lineas);
    }

    private function normalizarTelefono(?string $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        return $digits;
    }
}
