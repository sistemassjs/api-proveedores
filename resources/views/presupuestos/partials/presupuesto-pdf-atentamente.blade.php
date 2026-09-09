@php
    use App\Support\PresupuestoPdf;

    $mostrarAtentamente = PresupuestoPdf::debeMostrarBloqueAtentamenteDesdePayload($presupuesto);
    $atentamente = $mostrarAtentamente
        ? PresupuestoPdf::datosBloqueAtentamenteDesdePayload($presupuesto)
        : [];

    $nombreFmt = PresupuestoPdf::formatoNombrePersonaDocumento($atentamente['nombre'] ?? null);
    $puestoFmt = PresupuestoPdf::formatoPuestoEmpresaDocumento($atentamente['puesto'] ?? null);
    $empresaFmt = PresupuestoPdf::formatoPuestoEmpresaDocumento($atentamente['empresa'] ?? null);
    $telefonoRaw = trim((string) ($atentamente['telefono'] ?? ''));
    $correoRaw = trim((string) ($atentamente['correo'] ?? ''));

    $variant = (string) ($variant ?? 'default');
@endphp
@if ($mostrarAtentamente)
    <div class="atentamente-plain">
        @if ($variant === 'tailwind')
            <div class="tw-card-title atentamente-title">Atentamente:</div>
            <div class="atentamente-spacer" aria-hidden="true"></div>
            @if ($nombreFmt !== null)
                <div class="tw-receptor-strong">{{ $nombreFmt }}</div>
            @endif
            @if ($puestoFmt !== null)
                <div class="tw-receptor-line">{{ $puestoFmt }}</div>
            @endif
            @if ($empresaFmt !== null)
                <div class="tw-receptor-line">{{ $empresaFmt }}</div>
            @endif
            @if ($telefonoRaw !== '')
                <div class="tw-receptor-line">Tel. {{ $telefonoRaw }}</div>
            @endif
            @if ($correoRaw !== '')
                <div class="tw-receptor-line">{{ $correoRaw }}</div>
            @endif
        @else
            <div class="receptor-title atentamente-title">Atentamente:</div>
            <div class="atentamente-spacer" aria-hidden="true"></div>
            @if ($nombreFmt !== null)
                <div class="receptor-name">{{ $nombreFmt }}</div>
            @endif
            @if ($puestoFmt !== null)
                <div class="receptor-info">{{ $puestoFmt }}</div>
            @endif
            @if ($empresaFmt !== null)
                <div class="receptor-info">{{ $empresaFmt }}</div>
            @endif
            @if ($telefonoRaw !== '')
                <div class="receptor-info">Tel. {{ $telefonoRaw }}</div>
            @endif
            @if ($correoRaw !== '')
                <div class="receptor-info">{{ $correoRaw }}</div>
            @endif
        @endif
    </div>
@endif
