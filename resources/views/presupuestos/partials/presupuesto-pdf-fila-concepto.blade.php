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
    $monedaPrefijo = (string) ($monedaPrefijo ?? '$');
@endphp
@if ($esParrafo)
    <tr class="{{ $claseParrafo }}">
        <td>{{ $numeroFila }}</td>
        <td colspan="5">{{ \App\Support\PresupuestoParrafoPdf::descripcionParaPdf($concepto) }}</td>
    </tr>
@else
    @php
        $clasesFila = [];
        if ($tieneImagen) {
            $clasesFila[] = 'linea-con-imagen';
        }
        if ($tieneDesgloseMatriz) {
            $clasesFila[] = 'linea-con-matriz';
        }
    @endphp
    <tr @if (count($clasesFila) > 0) class="{{ implode(' ', $clasesFila) }}" @endif>
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
    @if ($tieneDesgloseMatriz)
        <tr class="concepto-matriz-desglose-tr">
            <td class="concepto-matriz-desglose-tr__num" aria-hidden="true"></td>
            <td colspan="5" class="concepto-matriz-desglose-tr__cell">
                <div class="concepto-matriz-desglose">
                    <table class="concepto-matriz-desglose-inner">
                        @foreach ($componentesMatriz as $comp)
                            @php
                                $compCant = (float) ($comp['cantidad'] ?? 0);
                                $compPu = (float) ($comp['precio_unitario'] ?? 0);
                                $compImp = isset($comp['importe'])
                                    ? (float) $comp['importe']
                                    : $compCant * $compPu;
                            @endphp
                            <tr>
                                <td class="concepto-matriz-desglose__desc">{{ $comp['descripcion'] ?? '—' }}</td>
                                <td class="concepto-matriz-desglose__cant">{{ rtrim(rtrim(number_format($compCant, 4, '.', ','), '0'), '.') }}</td>
                                <td class="concepto-matriz-desglose__unidad">{{ strtoupper($comp['unidad'] ?? 'PZA') }}</td>
                                <td class="concepto-matriz-desglose__money">
                                    <table class="concepto-matriz-desglose__money-inner">
                                        <tr>
                                            <td class="concepto-matriz-desglose__sym">{{ $monedaPrefijo }}</td>
                                            <td class="concepto-matriz-desglose__amt">{{ number_format($compPu, 2, '.', ',') }}</td>
                                        </tr>
                                    </table>
                                </td>
                                <td class="concepto-matriz-desglose__money concepto-matriz-desglose__imp">
                                    <table class="concepto-matriz-desglose__money-inner">
                                        <tr>
                                            <td class="concepto-matriz-desglose__sym">{{ $monedaPrefijo }}</td>
                                            <td class="concepto-matriz-desglose__amt">{{ number_format($compImp, 2, '.', ',') }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </td>
        </tr>
    @endif
@endif
