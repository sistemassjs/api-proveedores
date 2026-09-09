{{-- Encabezado documento (logo, emisor, folio). $headerCompact true = segunda hoja y siguientes. Requiere $presupuesto. --}}
@php
    $headerCompact = !empty($headerCompact);
@endphp
<div class="header{{ $headerCompact ? ' header--compact' : '' }}">
    <table class="header-content">
        <tr>
            <td class="logo-section">
                @php
                    $logoProveedorBase64 = $presupuesto['logo_proveedor_base64'] ?? null;
                    $nombreEmpresa =
                        $presupuesto['proveedor']->nombre_comercial ??
                        ($presupuesto['proveedor']->razon_social ?? 'P');
                    $inicial = strtoupper(substr($nombreEmpresa, 0, 1));
                @endphp
                @if ($logoProveedorBase64)
                    <img src="{{ $logoProveedorBase64 }}" alt="Logo" class="logo-img" />
                @else
                    <div class="logo-fallback">{{ $inicial }}</div>
                @endif
            </td>
            <td class="header-info">
                @include('presupuestos.partials.presupuesto-pdf-emisor-info-lines', [
                    'lineClass' => 'company-header-info',
                    'nameClass' => 'company-header-name',
                ])
            </td>
            <td class="folio-section">
                <div class="folio-label">Presupuesto</div>
                <div class="folio-number">
                    {{ $presupuesto['numero_presupuesto'] ?? 'PRES-000001' }}
                </div>
                @if (!$headerCompact && !empty($presupuesto['uuid']))
                    <div class="folio-uuid">{{ $presupuesto['uuid'] }}</div>
                @endif
                <div class="folio-date">
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
</div>
<div class="header-rule" role="presentation"></div>
