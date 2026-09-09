@php
    $variant = (string) ($variant ?? 'default');
    $terminosLista = $terminosLista ?? ($presupuesto['terminos_enunciados'] ?? []);
    $validacionesLista = $validacionesLista ?? ($presupuesto['validaciones_enunciados'] ?? []);
    $observacionesLista = $observacionesLista ?? ($presupuesto['observaciones_enunciados'] ?? []);
@endphp
@if ($variant === 'tailwind')
    @if (count($terminosLista) > 0)
        <div class="tw-terms">
            <h3>Términos y Condiciones</h3>
            <ul class="tw-terms-num">
                @foreach ($terminosLista as $texto)
                    <li>{{ $texto }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (count($observacionesLista) > 0)
        <div class="tw-terms">
            <h3>Observaciones</h3>
            <ul class="tw-obs-list">
                @foreach ($observacionesLista as $obs)
                    <li>{{ $obs }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@else
    @if (count($terminosLista) > 0)
        <div class="terminos-section">
            <div class="terminos-main-title">Términos y Condiciones</div>
            <ul class="terminos-list">
                @foreach ($terminosLista as $texto)
                    <li>{{ $texto }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (count($validacionesLista) > 0)
        <div class="terminos-section">
            <div class="terminos-title">Validación y Alcances</div>
            <ul class="observaciones-list">
                @foreach ($validacionesLista as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (count($observacionesLista) > 0)
        <div class="terminos-section observaciones-section">
            <div class="terminos-title observaciones-title">Observaciones Generales</div>
            <ul class="observaciones-list">
                @foreach ($observacionesLista as $obs)
                    <li>{{ $obs }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
