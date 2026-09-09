@php
    $numeroFila = (int) ($numeroFila ?? 1);
    $esParrafo = \App\Support\PresupuestoParrafoPdf::esLineaParrafo($concepto);
    $cantidad = $concepto['cantidad'] ?? 1;
    $precioUnitario = $concepto['precio_unitario'] ?? 0;
    $importe = $esParrafo ? 0 : $cantidad * $precioUnitario;
    $claseParrafo = ($variant ?? 'default') === 'tailwind' ? 'tw-linea-parrafo' : 'linea-parrafo';
    $imagenConcepto = ! $esParrafo ? ($concepto['imagen_base64'] ?? '') : '';
    $tieneImagen = is_string($imagenConcepto) && $imagenConcepto !== '';
@endphp
@if ($esParrafo)
    <tr class="{{ $claseParrafo }}">
        <td>{{ $numeroFila }}</td>
        <td colspan="5">{{ \App\Support\PresupuestoParrafoPdf::descripcionParaPdf($concepto) }}</td>
    </tr>
@else
    <tr @if ($tieneImagen) class="linea-con-imagen" @endif>
        <td>{{ $numeroFila }}</td>
        <td>
            {{ $concepto['descripcion'] ?? 'Sin descripción' }}
            @if ($tieneImagen)
                <div class="concepto-imagen-wrap">
                    <img src="{{ $imagenConcepto }}" alt="Imagen del concepto" class="concepto-imagen" />
                </div>
            @endif
        </td>
        <td>{{ number_format($cantidad, 2, '.', ',') }}</td>
        <td>{{ strtoupper($concepto['unidad'] ?? 'PZA') }}</td>
        <td>${{ number_format($precioUnitario, 2, '.', ',') }}</td>
        <td>${{ number_format($importe, 2, '.', ',') }}</td>
    </tr>
@endif
