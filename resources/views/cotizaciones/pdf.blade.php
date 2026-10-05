<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización {{ $solicitud->folio }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 24px; }
        .header { width: 100%; margin-bottom: 20px; }
        .header td { vertical-align: middle; }
        .logo { max-height: 56px; max-width: 160px; }
        .logo-nexprov { max-height: 40px; max-width: 120px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0b3d2e; }
        .meta { color: #555; margin-bottom: 16px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th, table.items td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table.items th { background: #e8f6f1; }
        .right { text-align: right; }
        .total { font-size: 14px; font-weight: bold; margin-top: 12px; text-align: right; }
        .footer { margin-top: 28px; font-size: 10px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
        .sugerencia { color: #b45309; font-size: 10px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 50%;">
                @if($logoEmpresa)
                    <img class="logo" src="{{ $logoEmpresa }}" alt="Logo empresa">
                @endif
                <h1>{{ $empresaNombre }}</h1>
                <div class="meta">Cotización {{ $solicitud->folio }} · {{ $fecha }}</div>
            </td>
            <td style="width: 50%; text-align: right;">
                @if($logoNexprov)
                    <img class="logo-nexprov" src="{{ $logoNexprov }}" alt="NexProv">
                @endif
            </td>
        </tr>
    </table>

    <p>
        <strong>Cliente:</strong> {{ $solicitud->cliente_nombre }}<br>
        <strong>Email:</strong> {{ $solicitud->cliente_email }}
        @if($solicitud->cliente_telefono)
            <br><strong>Teléfono:</strong> {{ $solicitud->cliente_telefono }}
        @endif
        @if($solicitud->cliente_whatsapp)
            <br><strong>WhatsApp:</strong> {{ $solicitud->cliente_whatsapp }}
        @endif
    </p>

    @if($solicitud->cliente_notas)
        <p><strong>Notas del cliente:</strong> {{ $solicitud->cliente_notas }}</p>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Unidad</th>
                <th class="right">P. unitario</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $i => $detalle)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $detalle->nombre }}
                        @if($detalle->codigo)
                            <br><small>{{ $detalle->codigo }}</small>
                        @endif
                        @if($detalle->es_sugerencia_empresa)
                            <br><span class="sugerencia">Sugerencia empresa{{ $detalle->motivo_sugerencia ? ': '.$detalle->motivo_sugerencia : '' }}</span>
                        @endif
                    </td>
                    <td>{{ rtrim(rtrim(number_format((float) $detalle->cantidad, 3, '.', ''), '0'), '.') }}</td>
                    <td>{{ $detalle->unidad ?: '—' }}</td>
                    <td class="right">${{ number_format((float) $detalle->precio_unitario, 2) }}</td>
                    <td class="right">${{ number_format((float) $detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">Total: ${{ number_format((float) $total, 2) }}</div>

    <div class="footer">
        Documento generado con NexProv. Los precios pueden diferir de los publicados en catálogo.
    </div>
</body>
</html>
