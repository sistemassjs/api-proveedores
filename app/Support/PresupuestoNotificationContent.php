<?php

namespace App\Support;

use App\Models\Presupuesto;

/**
 * Hechos y copy compartidos para notificaciones de presupuesto
 * (bandeja Capacitor/FCM = titulo corto; listado app = mensaje enriquecido).
 */
final class PresupuestoNotificationContent
{
    /**
     * @return array{
     *     folio: string,
     *     emisor_persona: string,
     *     emisor_tarjeta: string,
     *     emisor_empresa: string,
     *     descripcion: string,
     *     fecha_emision: string|null,
     *     fecha_emision_display: string,
     *     destinatario: string
     * }
     */
    public static function contexto(Presupuesto $presupuesto): array
    {
        $presupuesto->loadMissing(['user', 'proveedor', 'configEmisorPresupuesto']);

        $emisorPersona = trim((string) ($presupuesto->user?->name ?? ''));
        if ($emisorPersona === '') {
            $emisorPersona = 'Usuario';
        }

        $emisorTarjeta = self::nombreTarjetaEmisor($presupuesto);
        if ($emisorTarjeta === '') {
            $emisorTarjeta = $emisorPersona;
        }

        $emisorEmpresa = trim((string) (
            $presupuesto->proveedor?->nombre_comercial
            ?? $presupuesto->proveedor?->razon_social
            ?? $presupuesto->empresa_emisora_nombre_comercial
            ?? ''
        ));
        if ($emisorEmpresa === '') {
            $emisorEmpresa = 'Empresa';
        }

        $descripcion = trim((string) ($presupuesto->nombre_presupuesto ?? ''));
        if ($descripcion === '') {
            $descripcion = trim((string) ($presupuesto->concepto_general ?? ''));
        }

        $destinatario = trim((string) (
            $presupuesto->empresa_receptora_empresa
            ?? $presupuesto->empresa_receptora_nombre
            ?? ''
        ));
        if ($destinatario === '') {
            $destinatario = 'el cliente';
        }

        $fecha = $presupuesto->fecha_emision;
        $fechaIso = $fecha?->format('Y-m-d');
        $fechaDisplay = $fecha?->format('d/m/Y') ?? '—';

        $folio = trim((string) ($presupuesto->numero_presupuesto ?? ''));
        if ($folio === '') {
            $folio = 'PRES-—';
        }

        return [
            'folio' => $folio,
            'emisor_persona' => $emisorPersona,
            'emisor_tarjeta' => $emisorTarjeta,
            'emisor_empresa' => $emisorEmpresa,
            'descripcion' => $descripcion,
            'fecha_emision' => $fechaIso,
            'fecha_emision_display' => $fechaDisplay,
            'destinatario' => $destinatario,
        ];
    }

    /**
     * Título tipo oración para bandeja / cabecera in-app.
     *
     * Claves: enviado|recibido|actualizado|por_vencer|aceptado|rechazado|correccion
     */
    public static function tituloBandeja(Presupuesto $presupuesto, string $evento): string
    {
        $c = self::contexto($presupuesto);
        $folio = $c['folio'];
        $tarjeta = self::acortar(self::capitalizarNombre($c['emisor_tarjeta']), 36);
        $destinatario = self::acortar(self::capitalizarNombre($c['destinatario']), 36);

        return match (strtolower(trim($evento))) {
            'enviado', 'recibido' => sprintf('%s envió el presupuesto %s.', $tarjeta, $folio),
            'actualizado' => sprintf('%s actualizó el presupuesto %s.', $tarjeta, $folio),
            'por_vencer', 'por vencer' => sprintf('El presupuesto %s de %s está por vencer.', $folio, $tarjeta),
            'aceptado' => sprintf('%s aceptó el presupuesto %s.', $destinatario, $folio),
            'rechazado' => sprintf('%s rechazó el presupuesto %s.', $destinatario, $folio),
            'correccion', 'corrección' => sprintf(
                '%s solicitó corrección del presupuesto %s.',
                $destinatario,
                $folio
            ),
            default => sprintf('Presupuesto %s.', $folio),
        };
    }

    /**
     * Cuerpo corto: no repite quién/acción/folio del título.
     * Solo el contexto del evento + descripción del ppto si existe.
     */
    public static function mensajeConHechos(string $mensajeBase, Presupuesto $presupuesto): string
    {
        $c = self::contexto($presupuesto);
        $base = rtrim(trim($mensajeBase), " \t.");

        $parts = [];
        if ($base !== '') {
            $parts[] = $base;
        }
        if ($c['descripcion'] !== '') {
            $parts[] = self::acortar($c['descripcion'], 55);
        }

        if ($parts === []) {
            return 'Abre para ver el detalle.';
        }

        return implode(' · ', $parts).'.';
    }

    /**
     * Campos estructurados comunes para database / broadcast / FCM data.
     *
     * @param  string|null  $eventoActor  Clave del evento (enviado, aceptado, …) para el logo de quien actúa.
     * @return array<string, mixed>
     */
    public static function camposEstructurados(Presupuesto $presupuesto, ?string $eventoActor = null): array
    {
        $presupuesto->loadMissing(['proveedor', 'proveedorReceptor', 'empresaReceptora']);
        $c = self::contexto($presupuesto);

        return [
            'presupuesto_numero' => $c['folio'],
            'presupuesto_titulo' => $c['descripcion'] !== '' ? $c['descripcion'] : null,
            'usuario_envio_nombre' => $c['emisor_persona'],
            'empresa_emisora_nombre' => $c['emisor_empresa'],
            'empresa_logo_url' => self::empresaLogoUrlDelActor($presupuesto, $eventoActor),
            'fecha_emision' => $c['fecha_emision'],
            'fecha_emision_display' => $c['fecha_emision_display'],
            'destinatario_nombre' => $c['destinatario'],
        ];
    }

    /**
     * Logo de la empresa de quien provoca la notificación.
     * Emisor: enviado / recibido / actualizado / por_vencer.
     * Receptor/destinatario: aceptado / rechazado / correccion.
     */
    public static function empresaLogoUrlDelActor(Presupuesto $presupuesto, ?string $evento): ?string
    {
        $evento = strtolower(trim((string) $evento));

        if (in_array($evento, ['aceptado', 'rechazado', 'correccion', 'corrección'], true)) {
            return self::empresaLogoUrlReceptor($presupuesto);
        }

        return self::empresaLogoUrl($presupuesto);
    }

    /**
     * URL pública del logo del proveedor emisor (si está configurado).
     */
    public static function empresaLogoUrl(Presupuesto $presupuesto): ?string
    {
        $presupuesto->loadMissing(['proveedor']);

        return self::normalizarLogoPath($presupuesto->proveedor?->logo);
    }

    /**
     * Logo del destinatario que actúa (proveedor catálogo o cliente de cartera).
     */
    public static function empresaLogoUrlReceptor(Presupuesto $presupuesto): ?string
    {
        $presupuesto->loadMissing(['proveedorReceptor', 'empresaReceptora']);

        if ((int) ($presupuesto->proveedor_receptor_id ?? 0) > 0) {
            $url = $presupuesto->empresaReceptoraLogoUrlParaApi();
            if (is_string($url) && trim($url) !== '') {
                return $url;
            }
        }

        $cartera = $presupuesto->empresaReceptora;
        if ($cartera) {
            return self::normalizarLogoPath($cartera->logo_path ?? null);
        }

        return null;
    }

    private static function normalizarLogoPath(mixed $logo): ?string
    {
        if (! is_string($logo) || trim($logo) === '') {
            return null;
        }

        $logo = trim($logo);
        if (str_starts_with($logo, 'data:image')) {
            return null;
        }
        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            return $logo;
        }
        if (str_starts_with($logo, '/')) {
            return url($logo);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($logo);
    }

    /**
     * Nombre en tarjeta emisor (prefijo + nombre), o snapshot del documento.
     */
    private static function nombreTarjetaEmisor(Presupuesto $presupuesto): string
    {
        $config = $presupuesto->configEmisorPresupuesto;
        if ($config) {
            $completo = trim((string) $config->nombreCompletoParaDocumento());
            if ($completo !== '') {
                return $completo;
            }
        }

        return trim((string) ($presupuesto->empresa_emisora_nombre ?? ''));
    }

    private static function capitalizarNombre(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return $value;
        }

        // Conserva iniciales tipo "Ing." / "Lic." y capitaliza cada palabra.
        $parts = preg_split('/\s+/u', $value) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if (preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+\.$/u', $part) === 1) {
                $out[] = mb_strtoupper(mb_substr($part, 0, 1)).mb_strtolower(mb_substr($part, 1, -1)).'.';
                continue;
            }
            $out[] = mb_strtoupper(mb_substr($part, 0, 1)).mb_strtolower(mb_substr($part, 1));
        }

        return implode(' ', $out);
    }

    private static function acortar(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(1, $max - 1))).'…';
    }
}
