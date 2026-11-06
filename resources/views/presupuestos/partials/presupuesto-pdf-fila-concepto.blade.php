@php
    $numeroFila = (int) ($numeroFila ?? 1);
    $claveLinea = trim((string) ($concepto['clave'] ?? ''));
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
    /** Cebra por posición 0-based (no por id de BD ni nth-child). */
    $esFilaCebraPar = (bool) ($esFilaCebraPar ?? false);
    $colorRowEven = (string) ($colorRowEven ?? '#eff6ff');
    $colorRowOdd = (string) ($colorRowOdd ?? '#ffffff');
    $bgFila = $esFilaCebraPar ? $colorRowEven : $colorRowOdd;
    /** Inline: DomPDF no siempre aplica background vía clase/CSS var. */
    $styleBg = 'background-color:'.$bgFila.';';
@endphp
@if ($esParrafo)
    <tr class="{{ $claseParrafo }}{{ $esFilaCebraPar ? ' concepto-row-pair--even' : '' }}">
        <td class="concepto-num" style="{{ $styleBg }}">{{ $numeroFila }}</td>
        <td class="concepto-clave" style="{{ $styleBg }}">{{ $claveLinea }}</td>
        <td class="linea-parrafo__texto" colspan="5" style="{{ $styleBg }}">{{ \App\Support\PresupuestoParrafoPdf::descripcionParaPdf($concepto) }}</td>
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
        if ($esFilaCebraPar) {
            $clasesFila[] = 'concepto-row-pair--even';
        }
    @endphp
    <tr @if (count($clasesFila) > 0) class="{{ implode(' ', $clasesFila) }}" @endif>
        <td class="concepto-num" style="{{ $styleBg }}">{{ $numeroFila }}</td>
        <td class="concepto-clave" style="{{ $styleBg }}">{{ $claveLinea }}</td>
        <td style="{{ $styleBg }}">
            {{ $concepto['descripcion'] ?? 'Sin descripción' }}
            @if ($tieneImagen)
                <div class="concepto-imagen-wrap">
                    <img src="{{ $imagenConcepto }}" alt="Imagen del concepto" class="concepto-imagen" />
                </div>
            @endif
        </td>
        <td style="{{ $styleBg }}">{{ number_format($cantidad, 2, '.', ',') }}</td>
        <td style="{{ $styleBg }}">{{ strtoupper($concepto['unidad'] ?? 'PZA') }}</td>
        <td style="{{ $styleBg }}">{{ $monedaPrefijo }}{{ number_format($precioUnitario, 2, '.', ',') }}</td>
        <td style="{{ $styleBg }}">{{ $monedaPrefijo }}{{ number_format($importe, 2, '.', ',') }}</td>
    </tr>
    @if ($tieneDesgloseMatriz)
        <tr class="concepto-matriz-desglose-tr{{ $esFilaCebraPar ? ' concepto-row-pair--even' : '' }}">
            <td class="concepto-matriz-desglose-tr__num" aria-hidden="true" style="{{ $styleBg }}"></td>
            <td class="concepto-clave" aria-hidden="true" style="{{ $styleBg }}"></td>
            <td colspan="5" class="concepto-matriz-desglose-tr__cell" style="{{ $styleBg }}">
                <div class="concepto-matriz-desglose">
                    <table class="concepto-matriz-desglose-inner">
                        @foreach ($componentesMatriz as $comp)
                            @php
                                $compUnidad = mb_strtolower(trim((string) ($comp['unidad'] ?? '')));
                                $compEsParrafo = $compUnidad === 'párrafo' || $compUnidad === 'parrafo';
                                $compCant = (float) ($comp['cantidad'] ?? 0);
                                $compPu = (float) ($comp['precio_unitario'] ?? 0);
                                $compImp = isset($comp['importe'])
                                    ? (float) $comp['importe']
                                    : $compCant * $compPu;
                            @endphp
                            @if ($compEsParrafo)
                            <tr>
                                <td class="concepto-matriz-desglose__parrafo" colspan="5">{{ $comp['descripcion'] ?? '—' }}</td>
                            </tr>
                            @else
                            @php
                                $compCalculo = (string) ($comp['calculo'] ?? 'cantidad');
                                $compEsPorcentaje = $compCalculo === 'porcentaje_mo';
                                $compPct = (float) ($comp['porcentaje'] ?? 0);
                            @endphp
                            <tr>
                                <td class="concepto-matriz-desglose__desc">{{ $comp['descripcion'] ?? '—' }}</td>
                                <td class="concepto-matriz-desglose__cant">{{ $compEsPorcentaje ? rtrim(rtrim(number_format($compPct, 2, '.', ','), '0'), '.').'%' : rtrim(rtrim(number_format($compCant, 4, '.', ','), '0'), '.') }}</td>
                                <td class="concepto-matriz-desglose__unidad">{{ $compEsPorcentaje ? '%' : strtoupper($comp['unidad'] ?? 'PZA') }}</td>
                                <td class="concepto-matriz-desglose__money">
                                    @if ($compEsPorcentaje)
                                    <table class="concepto-matriz-desglose__money-inner">
                                        <tr>
                                            <td class="concepto-matriz-desglose__sym"></td>
                                            <td class="concepto-matriz-desglose__amt">—</td>
                                        </tr>
                                    </table>
                                    @else
                                    <table class="concepto-matriz-desglose__money-inner">
                                        <tr>
                                            <td class="concepto-matriz-desglose__sym">{{ $monedaPrefijo }}</td>
                                            <td class="concepto-matriz-desglose__amt">{{ number_format($compPu, 2, '.', ',') }}</td>
                                        </tr>
                                    </table>
                                    @endif
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
                            @endif
                        @endforeach
                    </table>
                </div>
            </td>
        </tr>
    @endif
@endif
