{{-- Cabecera emisor: solo datos del perfil de empresa (proveedor). Tarjetas Atte van en otro bloque. --}}
@php
    $p = $presupuesto['proveedor'];

    $emisorComercial = trim((string) ($p->nombre_comercial ?? ''));
    $emisorRazonSocial = trim((string) ($p->razon_social ?? ''));
    $emisorNombre =
        $emisorComercial !== ''
            ? $emisorComercial
            : ($emisorRazonSocial !== '' ? $emisorRazonSocial : 'Empresa Proveedora S.A. de C.V.');
    $emisorRfc = $p->rfc ?? null;

    $emisorRazonSocialLinea =
        $emisorRazonSocial !== '' && strcasecmp($emisorRazonSocial, $emisorNombre) !== 0
            ? $emisorRazonSocial
            : null;
    $emisorTel = trim((string) ($p->telefono ?? ''));
    $emisorEmail = trim((string) ($p->email ?? ''));

    $emisorDireccion = trim((string) ($p->direccion_empresa ?? ''));
    $ciudad = trim((string) ($p->ciudad ?? ''));
    $estadoGeo = trim((string) ($p->estado ?? ''));
    $emisorCiudadEstado = '';
    if ($ciudad !== '' || $estadoGeo !== '') {
        $emisorCiudadEstado = $ciudad . ($ciudad !== '' && $estadoGeo !== '' ? ', ' : '') . $estadoGeo;
        if ($emisorCiudadEstado !== '') {
            $emisorCiudadEstado .= ', México';
        }
    }
@endphp
<div class="{{ $nameClass ?? 'company-header-name' }}">{{ $emisorNombre }}</div>
@if ($emisorRfc)
    <div class="{{ $lineClass }}">{{ $emisorRfc }}</div>
@endif
@if ($emisorRazonSocialLinea)
    <div class="{{ $lineClass }}">{{ $emisorRazonSocialLinea }}</div>
@endif
@if (!$headerCompact && $emisorDireccion !== '')
    <div class="{{ $lineClass }}">{{ $emisorDireccion }}</div>
@endif
@if (!$headerCompact && $emisorCiudadEstado !== '')
    <div class="{{ $lineClass }}">{{ $emisorCiudadEstado }}</div>
@endif
@if (!$headerCompact && $emisorTel !== '')
    <div class="{{ $lineClass }}">Tel. {{ $emisorTel }}</div>
@endif
@if (!$headerCompact && $emisorEmail !== '')
    <div class="{{ $lineClass }}">{{ $emisorEmail }}</div>
@endif
