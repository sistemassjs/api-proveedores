@php
    $numeroFila = (int) ($numeroFila ?? 1);
    $esParrafo = \App\Support\PresupuestoParrafoPdf::esLineaParrafo($concepto);
    $cantidad = $concepto['cantidad'] ?? 1;
    $precioUnitario = $concepto['precio_unitario'] ?? 0;
    $importe = $esParrafo ? 0 : $cantidad * $precioUnitario;
    $claseParrafo = ($variant ?? 'default') === 'tailwind' ? 'tw-linea-parrafo' : 'linea-parrafo';
    $imagenConcepto = ! $esParrafo ? ($concepto['imagen_base64'] ?? '') : '';
    $tieneImagen = is_string($imagenConcepto) && $imagenConcepto !== '';
    $mostrarMatriz = (bool) ($mostrarMatrizCostos ?? false);
    $componentesMatriz = is_array($concepto['componentes'] ?? null) ? $concepto['componentes'] : [];
    $tieneDesgloseMatriz = $mostrarMatriz && ! $esParrafo && count($componentesMatriz) > 0;
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
            @if ($tieneDesgloseMatriz)
                <div class="concepto-matriz-desglose">
                    @foreach ($componentesMatriz as $comp)
                        @php
                            $compCant = (float) ($comp['cantidad'] ?? 0);
                            $compPu = (float) ($comp['precio_unitario'] ?? 0);
                            $compImp = isset($comp['importe'])
                                ? (float) $comp['importe']
                                : $compCant * $compPu;
                        @endphp
                        <div class="concepto-matriz-desglose__row">
                            <span class="concepto-matriz-desglose__desc">{{ $comp['descripcion'] ?? '—' }}</span>
                            <span class="concepto-matriz-desglose__meta">
                                {{ number_format($compCant, 4, '.', ',') }}
                                · {{ strtoupper($comp['unidad'] ?? 'PZA') }}
                                · ${{ number_format($compPu, 2, '.', ',') }}
                                · ${{ number_format($compImp, 2, '.', ',') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
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
