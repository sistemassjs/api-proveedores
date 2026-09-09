{{-- Encabezado Tailwind. $headerCompact true = hoja compacta (anexos). Requiere $presupuesto. --}}
@php
    $headerCompact = !empty($headerCompact);
@endphp
<table class="tw-header-wrap{{ $headerCompact ? ' tw-header-wrap--compact' : '' }}" cellpadding="0" cellspacing="0">
    <tr>
        <td class="tw-header-main">
            <table class="tw-header-top">
                @php
                    $logoProveedorBase64 = $presupuesto['logo_proveedor_base64'] ?? null;
                    $nombreEmpresa =
                        $presupuesto['proveedor']->nombre_comercial ??
                        ($presupuesto['proveedor']->razon_social ?? 'P');
                    $inicial = strtoupper(substr($nombreEmpresa, 0, 1));
                    $maxLogoWidthMm = $headerCompact ? 26.0 : 40.0;
                    $maxLogoHeightMm = $headerCompact ? 15.0 : 30.0;
                    $minLogoContainerWidthMm = 0.01;
                    $minLogoContainerHeightMm = 0.01;
                    $logoGapRightMm = $headerCompact ? 2.5 : 5;
                    $logoBoxWidthMm = $minLogoContainerWidthMm;
                    $logoBoxHeightMm = $minLogoContainerHeightMm;

                    if ($logoProveedorBase64) {
                        $logoRaw = $logoProveedorBase64;
                        if (str_starts_with($logoRaw, 'data:image')) {
                            $logoParts = explode(',', $logoRaw, 2);
                            $logoRaw = $logoParts[1] ?? '';
                        }

                        $logoBinary = base64_decode($logoRaw, true);
                        if ($logoBinary !== false) {
                            $logoInfo = @getimagesizefromstring($logoBinary);
                            if (is_array($logoInfo) && !empty($logoInfo[0]) && !empty($logoInfo[1])) {
                                $logoWidthPx = (float) $logoInfo[0];
                                $logoHeightPx = (float) $logoInfo[1];

                                if ($logoWidthPx >= $logoHeightPx) {
                                    $logoBoxWidthMm = $maxLogoWidthMm;
                                    $logoBoxHeightMm =
                                        $logoBoxWidthMm * ($logoHeightPx / $logoWidthPx);
                                } else {
                                    $logoBoxHeightMm = $maxLogoHeightMm;
                                    $logoBoxWidthMm =
                                        $logoBoxHeightMm * ($logoWidthPx / $logoHeightPx);
                                }

                                $scaleDownFactor = min(
                                    1,
                                    $maxLogoWidthMm / max($logoBoxWidthMm, 0.0001),
                                    $maxLogoHeightMm / max($logoBoxHeightMm, 0.0001),
                                );
                                $logoBoxWidthMm *= $scaleDownFactor;
                                $logoBoxHeightMm *= $scaleDownFactor;

                                $logoBoxWidthMm = max(
                                    $minLogoContainerWidthMm,
                                    min($maxLogoWidthMm, $logoBoxWidthMm),
                                );
                                $logoBoxHeightMm = max(
                                    $minLogoContainerHeightMm,
                                    min($maxLogoHeightMm, $logoBoxHeightMm),
                                );
                            }
                        }
                    }

                    $logoCellWidthMm = $logoBoxWidthMm + $logoGapRightMm;
                @endphp
                <tr>
                    <td class="tw-logo-cell"
                        style="width: {{ number_format($logoCellWidthMm, 2, '.', '') }}mm; padding-right: {{ number_format($logoGapRightMm, 2, '.', '') }}mm;">
                        @if ($logoProveedorBase64)
                            <div class="tw-logo-box"
                                style="width: {{ number_format($logoBoxWidthMm, 2, '.', '') }}mm; height: {{ number_format($logoBoxHeightMm, 2, '.', '') }}mm;">
                                <img src="{{ $logoProveedorBase64 }}" alt="Logo" class="tw-logo-img" />
                            </div>
                        @else
                            <div class="tw-logo-box"
                                style="width: {{ number_format($logoBoxWidthMm, 2, '.', '') }}mm; height: {{ number_format($logoBoxHeightMm, 2, '.', '') }}mm;">
                                <div class="tw-logo-fallback"><span>{{ $inicial }}</span></div>
                            </div>
                        @endif
                    </td>
                    <td class="tw-emisor-cell">
                        @include('presupuestos.partials.presupuesto-pdf-emisor-info-lines', [
                            'lineClass' => 'tw-emisor-line',
                            'nameClass' => 'tw-emisor-name',
                        ])
                    </td>
                    <td class="tw-folio-cell">
                        <div class="tw-badge-label">Presupuesto</div>
                        <div class="tw-badge-folio">
                            {{ $presupuesto['numero_presupuesto'] ?? 'PRES-000001' }}</div>
                        @if (!$headerCompact && !empty($presupuesto['uuid']))
                            <div class="tw-uuid">{{ $presupuesto['uuid'] }}</div>
                        @endif
                        <div class="tw-date">
                            @php
                                $fecha = $presupuesto['fecha_emision'] ?? now();
                                if (is_string($fecha)) {
                                    $fecha = \Carbon\Carbon::parse($fecha);
                                }
                                $fechaFormateada = $fecha
                                    ->locale('es')
                                    ->translatedFormat('d \d\e F \d\e\l Y');
                            @endphp
                            {{ $fechaFormateada }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<div class="tw-header-rule" role="presentation"></div>
