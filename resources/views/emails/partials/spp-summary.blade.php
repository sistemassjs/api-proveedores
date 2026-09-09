@php
    $sppFolio = $sppFolio ?? null;
    $sppEstado = $sppEstado ?? 'En proceso';
    $sppMonto = $sppMonto ?? null;
    $sppFecha = $sppFecha ?? now();

    $formatFechaCorreo = static function ($fecha): string {
        if (empty($fecha)) {
            return 'No disponible';
        }

        $dt = $fecha instanceof \Carbon\CarbonInterface
            ? $fecha->copy()
            : \Carbon\Carbon::parse($fecha);

        $dt->locale('es');
        $dt->timezone('America/Mexico_City');

        $periodo = $dt->format('A') === 'AM' ? 'a.m.' : 'p.m.';

        return $dt->translatedFormat('j \\d\\e F \\d\\e Y').' '.$dt->format('h:i').' '.$periodo;
    };
@endphp

<div style="border:1px solid #dbe3ee;border-radius:10px;background:#ffffff;margin:16px 0;overflow:hidden;">
  <div style="background:#f8fafc;color:#0f4c81;font-weight:700;padding:10px 12px;font-size:14px;">Resumen solicitud de pago</div>
  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;color:#334155;">
    <tr>
      <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;width:38%;">Folio</td>
      <td style="padding:8px;border:1px solid #e2e8f0;">{{ $sppFolio ?: 'No disponible' }}</td>
    </tr>
    <tr>
      <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;">Estado</td>
      <td style="padding:8px;border:1px solid #e2e8f0;">{{ $sppEstado }}</td>
    </tr>
    <tr>
      <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;">Fecha</td>
      <td style="padding:8px;border:1px solid #e2e8f0;">{{ $formatFechaCorreo($sppFecha) }}</td>
    </tr>
    @if(!is_null($sppMonto))
      <tr>
        <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;">Monto</td>
        <td style="padding:8px;border:1px solid #e2e8f0;color:#0f4c81;font-weight:700;">${{ number_format((float) $sppMonto, 2) }}</td>
      </tr>
    @endif
  </table>
</div>
