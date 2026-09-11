<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de importación #{{ $audit->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.45;
        }
        .header {
            background-color: #1d4e89;
            color: #ffffff;
            padding: 18px 22px;
            margin-bottom: 18px;
        }
        .header h1 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .header p {
            font-size: 11px;
            color: #e2e8f0;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .meta td.label {
            width: 28%;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
        }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1d4e89;
            margin: 16px 0 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #1d4e89;
        }
        .cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 8px;
        }
        .cards td {
            width: 25%;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 8px;
            text-align: center;
        }
        .cards .value {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            display: block;
        }
        .cards .label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .cards .value.success { color: #15803d; }
        .cards .value.warn { color: #b45309; }
        .cards .value.danger { color: #b91c1c; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.data th {
            background: #1d4e89;
            color: #fff;
            text-align: left;
            padding: 7px 8px;
            font-size: 10px;
        }
        table.data td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            font-size: 10px;
        }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-err { background: #fee2e2; color: #991b1b; }
        .badge-warn { background: #ffedd5; color: #9a3412; }
        .muted { color: #64748b; font-size: 10px; }
        .footer {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 9px;
            text-align: center;
        }
        .empty {
            padding: 12px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $proveedorNombre = $proveedor->nombre_comercial
        ?? $proveedor->razon_social
        ?? ('Proveedor #'.$audit->proveedor_id);
    $estado = $audit->estado ?? 'desconocido';
    $badgeClass = $estado === 'completado' ? 'badge-ok' : ($estado === 'error' ? 'badge-err' : 'badge-warn');
    $stats = $data['estadisticas'] ?? [];
    $catalogos = $data['breakdown_catalogos'] ?? [];
    $errores = $data['errores_detalle'] ?? [];
    $opus = $data['opus'] ?? null;
    $soloErrores = ($type ?? 'report') === 'errors';
@endphp

<div class="header">
    <h1>Reporte de importación CSV</h1>
    <p>{{ $proveedorNombre }} · Audit #{{ $audit->id }} · {{ now()->format('d/m/Y H:i') }}</p>
</div>

<table class="meta">
    <tr>
        <td class="label">Estado</td>
        <td><span class="badge {{ $badgeClass }}">{{ $estado }}</span></td>
        <td class="label">Archivo</td>
        <td>{{ basename($audit->archivo ?? 'N/A') }}</td>
    </tr>
    <tr>
        <td class="label">Inicio</td>
        <td>{{ optional($audit->inicio_proceso)->format('d/m/Y H:i:s') ?? '—' }}</td>
        <td class="label">Fin</td>
        <td>{{ optional($audit->fin_proceso)->format('d/m/Y H:i:s') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Duración</td>
        <td>{{ $data['duracion_texto'] ?? (($data['processing_time'] ?? 0).'s') }}</td>
        <td class="label">Plantilla</td>
        <td>{{ ($audit->plantilla_version ?? '1.0') }} ({{ $audit->plantilla_fecha ?? '—' }})</td>
    </tr>
</table>

@if (! $soloErrores)
    <div class="section-title">Productos</div>
    <table class="cards">
        <tr>
            <td>
                <span class="value">{{ number_format($stats['total_procesados'] ?? 0) }}</span>
                <span class="label">Total</span>
            </td>
            <td>
                <span class="value success">{{ number_format($stats['nuevos'] ?? 0) }}</span>
                <span class="label">Nuevos</span>
            </td>
            <td>
                <span class="value warn">{{ number_format($stats['actualizados'] ?? 0) }}</span>
                <span class="label">Actualizados</span>
            </td>
            <td>
                <span class="value {{ ($stats['errores'] ?? 0) > 0 ? 'danger' : '' }}">{{ number_format($stats['errores'] ?? 0) }}</span>
                <span class="label">Errores</span>
            </td>
        </tr>
    </table>
    <p class="muted">Tasa de éxito: {{ $stats['tasa_exito'] ?? 0 }}%</p>

    <div class="section-title">Catálogos relacionados</div>
    <table class="data">
        <thead>
            <tr>
                <th>Catálogo</th>
                <th>Total</th>
                <th>Nuevas</th>
                <th>Existentes</th>
                <th>Errores</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['marcas' => 'Marcas', 'categorias' => 'Categorías', 'unidades' => 'Unidades'] as $key => $label)
                @php $row = $catalogos[$key] ?? []; @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ number_format($row['total'] ?? 0) }}</td>
                    <td>{{ number_format($row['nuevas'] ?? 0) }}</td>
                    <td>{{ number_format($row['existentes'] ?? 0) }}</td>
                    <td>{{ number_format($row['errores'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (is_array($opus))
        <div class="section-title">Homologación OPUS</div>
        <table class="meta">
            <tr>
                <td class="label">Homologados</td>
                <td>{{ number_format($opus['homologados'] ?? 0) }}</td>
                <td class="label">Sin match</td>
                <td>{{ number_format($opus['sin_match'] ?? 0) }}</td>
            </tr>
        </table>
    @endif
@endif

<div class="section-title">Detalle de errores{{ $soloErrores ? '' : ' (muestra)' }}</div>
@if (empty($errores))
    <div class="empty">Sin errores registrados en esta importación.</div>
@else
    <table class="data">
        <thead>
            <tr>
                <th style="width: 12%;">Fila</th>
                <th style="width: 18%;">Tipo</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($errores as $error)
                @php
                    $fila = $error['row'] ?? ($error['fila'] ?? '—');
                    $tipo = $error['tipo_error'] ?? ($error['tipo'] ?? 'validacion');
                    $msg = $error['error'] ?? ($error['message'] ?? ($error['mensaje'] ?? json_encode($error, JSON_UNESCAPED_UNICODE)));
                    if (is_array($msg)) {
                        $msg = implode('; ', $msg);
                    }
                @endphp
                <tr>
                    <td>{{ $fila }}</td>
                    <td>{{ $tipo }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string) $msg, 180) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (($data['errores_omitidos'] ?? 0) > 0)
        <p class="muted">Se omitieron {{ $data['errores_omitidos'] }} errores adicionales en el PDF.</p>
    @endif
@endif

<div class="footer">
    Generado por {{ config('app.name', 'API Proveedores') }} · Importación masiva (csv-import-servidor)
</div>
</body>
</html>
