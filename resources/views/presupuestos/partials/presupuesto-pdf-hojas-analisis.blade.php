@php
    $hojasAnalisis = is_array($hojasAnalisis ?? null) ? $hojasAnalisis : [];
    $variant = (string) ($variant ?? 'default');
    $esTailwind = $variant === 'tailwind';
    $pageBreak = $esTailwind ? 'tw-page-break' : 'page-break';
    $wrapClass = $esTailwind ? 'tw-hoja-analisis' : 'hoja-analisis';
    $monedaPrefijo = $monedaPrefijo ?? '$';
    $fmt = static function ($n, int $dec = 2): string {
        return number_format((float) $n, $dec, '.', ',');
    };
    $fmtCant = static function ($n): string {
        $texto = rtrim(rtrim(number_format((float) $n, 4, '.', ','), '0'), '.');

        return $texto === '' ? '0' : $texto;
    };
    $fmtPct = static function ($n): string {
        $numero = (float) $n;
        $texto = abs($numero - round($numero)) < 0.001
            ? (string) (int) round($numero)
            : rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.');

        return $texto.'%';
    };
@endphp
@if (count($hojasAnalisis) > 0)
    @foreach ($hojasAnalisis as $hojaIndex => $hoja)
        <div class="{{ $pageBreak }}"></div>
        <div class="pdf-seccion pdf-seccion--hoja-analisis {{ $wrapClass }}">
            <div class="{{ $esTailwind ? 'tw-hoja-analisis__header' : 'hoja-analisis__header' }}">
                <div class="{{ $esTailwind ? 'tw-hoja-analisis__title' : 'hoja-analisis__title' }}">
                    Análisis de precios unitarios
                </div>
                <div class="{{ $esTailwind ? 'tw-hoja-analisis__meta' : 'hoja-analisis__meta' }}">
                    <strong>{{ $hoja['clave'] ?? '' }}</strong>
                    · {{ strtoupper((string) ($hoja['unidad'] ?? '')) }}
                    · Cant. {{ $fmtCant($hoja['cantidad'] ?? 1) }}
                </div>
            </div>

            <p class="{{ $esTailwind ? 'tw-hoja-analisis__desc' : 'hoja-analisis__desc' }}">
                {{ $hoja['descripcion'] ?? '' }}
            </p>

            @foreach (($hoja['grupos'] ?? []) as $grupo)
                <table class="{{ $esTailwind ? 'tw-hoja-analisis__table' : 'hoja-analisis__table' }}">
                    <thead>
                        <tr>
                            <th colspan="6" class="{{ $esTailwind ? 'tw-hoja-analisis__group' : 'hoja-analisis__group' }}">
                                {{ $grupo['etiqueta'] ?? '' }}
                            </th>
                        </tr>
                        <tr>
                            <th class="col-clave">Clave</th>
                            <th class="col-desc">Descripción</th>
                            <th class="col-cant">Cant.</th>
                            <th class="col-unidad">Unidad</th>
                            <th class="col-money">Costo</th>
                            <th class="col-money">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($grupo['filas'] ?? []) as $fila)
                            @php
                                $esPct = ($fila['calculo'] ?? '') === 'porcentaje_mo';
                            @endphp
                            <tr>
                                <td class="col-clave">{{ $fila['clave'] ?? '' }}</td>
                                <td class="col-desc">{{ $fila['descripcion'] ?? '' }}</td>
                                <td class="col-cant">
                                    @if ($esPct)
                                        {{ $fmtPct($fila['porcentaje'] ?? 0) }}
                                    @else
                                        {{ $fmtCant($fila['cantidad'] ?? 0) }}
                                    @endif
                                </td>
                                <td class="col-unidad">{{ $esPct ? '%' : strtoupper((string) ($fila['unidad'] ?? '')) }}</td>
                                <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($fila['precio_unitario'] ?? 0) }}</td>
                                <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($fila['importe'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        <tr class="{{ $esTailwind ? 'tw-hoja-analisis__subtotal' : 'hoja-analisis__subtotal' }}">
                            <td colspan="5">Subtotal {{ $grupo['etiqueta'] ?? '' }}</td>
                            <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($grupo['subtotal'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endforeach

            @php $sc = is_array($hoja['sobrecostos'] ?? null) ? $hoja['sobrecostos'] : []; @endphp
            <table class="{{ $esTailwind ? 'tw-hoja-analisis__totales' : 'hoja-analisis__totales' }}">
                <tr>
                    <td>Costo directo</td>
                    <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($hoja['costo_directo'] ?? 0) }}</td>
                </tr>
                @foreach ([
                    'oficina' => 'Oficina',
                    'campo' => 'Campo',
                    'financiamiento' => 'Financiamiento',
                    'utilidad' => 'Utilidad',
                    'cargos' => 'Cargos',
                    'otros' => 'Otros',
                ] as $clave => $etiqueta)
                    @php
                        $importeSc = (float) ($sc[$clave] ?? 0);
                        $pctSc = (float) (($sc['pct'][$clave] ?? 0));
                    @endphp
                    @if ($importeSc >= 0.005 || $pctSc >= 0.01)
                        <tr>
                            <td>{{ $etiqueta }} @if ($pctSc >= 0.01)<span class="pct">({{ $fmtPct($pctSc) }})</span>@endif</td>
                            <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($importeSc) }}</td>
                        </tr>
                    @endif
                @endforeach
                <tr>
                    <td>Total sobrecostos</td>
                    <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($sc['total'] ?? 0) }}</td>
                </tr>
                <tr class="{{ $esTailwind ? 'tw-hoja-analisis__pu' : 'hoja-analisis__pu' }}">
                    <td>Precio unitario</td>
                    <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($hoja['precio_unitario'] ?? 0) }}</td>
                </tr>
                @if ((float) ($hoja['cantidad'] ?? 1) > 1.0001)
                    <tr>
                        <td>Importe ({{ $fmtCant($hoja['cantidad'] ?? 1) }} × P.U.)</td>
                        <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($hoja['importe'] ?? 0) }}</td>
                    </tr>
                @endif
                @if (!empty($hoja['con_iva']) && (float) ($hoja['iva_porcentaje'] ?? 0) > 0)
                    <tr>
                        <td>IVA ({{ $fmtPct($hoja['iva_porcentaje'] ?? 0) }})</td>
                        <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($hoja['iva_importe'] ?? 0) }}</td>
                    </tr>
                @endif
                <tr class="{{ $esTailwind ? 'tw-hoja-analisis__total' : 'hoja-analisis__total' }}">
                    <td>Total</td>
                    <td class="col-money">{{ $monedaPrefijo }}{{ $fmt($hoja['total'] ?? 0) }}</td>
                </tr>
            </table>
        </div>
    @endforeach
@endif
