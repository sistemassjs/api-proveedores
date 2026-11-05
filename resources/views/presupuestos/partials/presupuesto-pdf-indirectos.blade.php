@php
    $filasIndirectos = app(\App\Services\Presupuesto\PresupuestoMatrizCalculoService::class)
        ->filasIndirectosDocumento(is_array($presupuesto ?? null) ? $presupuesto : []);
    $esTailwind = ($variant ?? 'default') === 'tailwind';
    $tablaIndirectos = $esTailwind ? 'tw-totals-table indirectos-documento' : 'totales-table indirectos-documento';
    $signo = $esTailwind ? 'tw-totals-money-sign-col' : 'totales-money-sign-col';
    $importe = $esTailwind ? 'tw-totals-money-amount-col' : 'totales-money-amount-col';
    $textoPorcentaje = static function ($porcentaje): string {
        if ($porcentaje === null || (float) $porcentaje <= 0) {
            return '';
        }
        $numero = (float) $porcentaje;
        $texto = abs($numero - round($numero)) < 0.001
            ? (string) (int) round($numero)
            : rtrim(rtrim(number_format($numero, 2, '.', ''), '0'), '.');

        return $texto.'%';
    };
@endphp
@if (count($filasIndirectos) > 0)
    <table class="{{ $tablaIndirectos }}">
        @foreach ($filasIndirectos as $fila)
            <tr>
                <td>{{ $fila['etiqueta'] }}:</td>
                <td class="indirectos-pct">{{ $textoPorcentaje($fila['porcentaje'] ?? null) }}</td>
                <td class="{{ $signo }}">{{ $monedaPrefijo }}</td>
                <td class="{{ $importe }}">{{ number_format($fila['importe'], 2, '.', ',') }}</td>
            </tr>
        @endforeach
    </table>
@endif
