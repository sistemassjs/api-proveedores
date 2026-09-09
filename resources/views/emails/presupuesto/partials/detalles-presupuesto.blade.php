@php
  $emisorNombre = $presupuesto->proveedor?->nombre_comercial ?? $presupuesto->proveedor?->razon_social ?? '—';
  $moneda = $presupuesto->term_cond_moneda ?? 'MXN';
  $subtotal = number_format((float) $presupuesto->subtotal, 2);
  $total = number_format((float) $presupuesto->total, 2);
  $ivaPct = $presupuesto->iva_porcentaje;
  $conIva = $presupuesto->con_iva;
  $formatFechaCorreo = static function ($fecha): string {
      if (empty($fecha)) {
          return '—';
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
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;color:#334155;border:1px solid #dbe3ee;border-radius:8px;overflow:hidden;">
  <tr>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;background:#f8fafc;"><strong>Proveedor emisor</strong></td>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;text-align:right;">{{ $emisorNombre }}</td>
  </tr>
  <tr>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;background:#f8fafc;"><strong>Cliente / empresa</strong></td>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;text-align:right;">{{ $presupuesto->empresa_receptora_empresa ?? $presupuesto->empresa_receptora_nombre ?? '—' }}</td>
  </tr>
  <tr>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;background:#f8fafc;"><strong>Contacto</strong></td>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;text-align:right;">{{ $presupuesto->empresa_receptora_nombre ?? '—' }} @if($presupuesto->empresa_receptora_correo)<br><span style="font-size:13px;color:#64748b;">{{ $presupuesto->empresa_receptora_correo }}</span>@endif</td>
  </tr>
  <tr>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;background:#f8fafc;"><strong>Concepto general</strong></td>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;text-align:right;">{{ Str::limit(strip_tags($presupuesto->concepto_general), 200) }}</td>
  </tr>
  <tr>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;background:#f8fafc;"><strong>Fecha emisión</strong></td>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;text-align:right;">{{ $formatFechaCorreo($presupuesto->fecha_emision) }}</td>
  </tr>
  @if($presupuesto->fecha_vencimiento)
  <tr>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;background:#f8fafc;"><strong>Vigencia hasta</strong></td>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;text-align:right;">{{ $formatFechaCorreo($presupuesto->fecha_vencimiento) }}</td>
  </tr>
  @endif
  <tr>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;background:#f8fafc;"><strong>Subtotal</strong></td>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;text-align:right;">${{ $subtotal }} {{ $moneda }}</td>
  </tr>
  @if($conIva)
  <tr>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;background:#f8fafc;"><strong>IVA @if($ivaPct)({{ $ivaPct }}%)@endif</strong></td>
    <td style="padding:10px 12px;border-bottom:1px solid #e9ecef;text-align:right;">${{ number_format((float) $presupuesto->iva_total, 2) }} {{ $moneda }}</td>
  </tr>
  @endif
  <tr>
    <td style="padding:12px;background:#eff6ff;font-size:16px;"><strong>Total</strong></td>
    <td style="padding:12px;background:#eff6ff;text-align:right;font-size:18px;font-weight:700;color:#0f4c81;">${{ $total }} {{ $moneda }}</td>
  </tr>
</table>
