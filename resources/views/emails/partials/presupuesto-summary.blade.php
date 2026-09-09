@php
    $presupuestoNumero = $presupuestoNumero ?? ($presupuesto->numero_presupuesto ?? null);
    $presupuestoTotal = $presupuestoTotal ?? ($presupuesto->total ?? null);
    $presupuestoFechaEmision = $presupuestoFechaEmision ?? ($presupuesto->fecha_emision ?? null);
    $presupuestoFechaVencimiento = $presupuestoFechaVencimiento ?? ($presupuesto->fecha_vencimiento ?? null);
    $presupuestoCliente = $presupuestoCliente ?? ($presupuesto->empresa_receptora_empresa ?? $presupuesto->empresa_receptora_nombre ?? null);

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
  <div style="background:#f8fafc;color:#0f4c81;font-weight:700;padding:10px 12px;font-size:14px;">Resumen presupuesto</div>
  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px;color:#334155;">
    <tr>
      <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;width:38%;">Número</td>
      <td style="padding:8px;border:1px solid #e2e8f0;">{{ $presupuestoNumero ?: 'No disponible' }}</td>
    </tr>
    <tr>
      <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;">Cliente</td>
      <td style="padding:8px;border:1px solid #e2e8f0;">{{ $presupuestoCliente ?: 'No disponible' }}</td>
    </tr>
    @if(!is_null($presupuestoTotal))
      <tr>
        <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;">Total</td>
        <td style="padding:8px;border:1px solid #e2e8f0;color:#0f4c81;font-weight:700;">${{ number_format((float) $presupuestoTotal, 2) }}</td>
      </tr>
    @endif
    <tr>
      <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;">Emisión</td>
      <td style="padding:8px;border:1px solid #e2e8f0;">
        {{ $formatFechaCorreo($presupuestoFechaEmision) }}
      </td>
    </tr>
    @if($presupuestoFechaVencimiento)
      <tr>
        <td style="padding:8px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600;">Vencimiento</td>
        <td style="padding:8px;border:1px solid #e2e8f0;">
          {{ $formatFechaCorreo($presupuestoFechaVencimiento) }}
        </td>
      </tr>
    @endif
  </table>
</div>
