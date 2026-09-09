@php
    $appName = (string) config('app.name', 'Aplicacion');
    $logoAlt = trim($appName) !== '' ? $appName : 'Aplicacion';
    $fallbackInitial = strtoupper(mb_substr($logoAlt, 0, 1));

    // Prioridad de logo en correos: proveedor (si existe en contexto) -> app.
    if (empty($logoAppDataUri) && isset($proveedor) && $proveedor) {
        $logoAppDataUri = \App\Support\EmailLogoHelper::proveedorDataUri($proveedor);
    }

    if (empty($logoAppDataUri) && isset($presupuesto) && $presupuesto?->proveedor) {
        $logoAppDataUri = \App\Support\EmailLogoHelper::proveedorDataUri($presupuesto->proveedor);
    }

    if (empty($logoAppDataUri) && isset($solicitudPago) && $solicitudPago?->proveedor) {
        $logoAppDataUri = \App\Support\EmailLogoHelper::proveedorDataUri($solicitudPago->proveedor);
    }

    $logoAppDataUri = $logoAppDataUri ?? \App\Support\EmailLogoHelper::logoGestionPlusDataUri();
@endphp

@if(!empty($logoAppDataUri))
<img src="{{ $logoAppDataUri }}" alt="{{ $logoAlt }}" width="80" style="max-width:80px;height:auto;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;">
@else
<div style="width:48px;height:48px;border-radius:8px;background-color:#1e88e5;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:22px;line-height:48px;font-weight:700;text-align:center;margin:0 auto 12px auto;">
{{ $fallbackInitial !== '' ? $fallbackInitial : 'A' }}
</div>
@endif
