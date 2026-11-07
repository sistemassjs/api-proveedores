<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Análisis de precios unitarios</title>
    <style>
        @page {
            margin: 14mm 12mm 14mm 12mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .page-break {
            page-break-before: always;
        }
        .hoja-analisis {
            width: 100%;
        }
        .hoja-analisis__header {
            margin-bottom: 2mm;
            padding-bottom: 1.5mm;
            border-bottom: 1.2px solid #334155;
        }
        .hoja-analisis__title {
            font-size: 11pt;
            font-weight: 700;
            margin: 0 0 1mm;
        }
        .hoja-analisis__meta {
            font-size: 8pt;
            color: #475569;
        }
        .hoja-analisis__desc {
            margin: 0 0 3mm;
            font-size: 8pt;
            line-height: 1.35;
        }
        .hoja-analisis__table,
        .hoja-analisis__totales {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
            table-layout: fixed;
        }
        .hoja-analisis__table th,
        .hoja-analisis__table td,
        .hoja-analisis__totales td {
            padding: 1.1mm 1.2mm;
            border-bottom: 0.4px solid #e2e8f0;
            vertical-align: top;
        }
        .hoja-analisis__group {
            text-align: left;
            background: #f1f5f9;
            font-weight: 700;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .hoja-analisis__table .col-clave { width: 14%; }
        .hoja-analisis__table .col-desc { width: 38%; }
        .hoja-analisis__table .col-cant { width: 12%; text-align: right; }
        .hoja-analisis__table .col-unidad { width: 10%; text-align: center; }
        .hoja-analisis__table .col-money,
        .hoja-analisis__totales .col-money {
            width: 13%;
            text-align: right;
            white-space: nowrap;
        }
        .hoja-analisis__subtotal td {
            font-weight: 700;
            background: #f8fafc;
        }
        .hoja-analisis__totales td:first-child {
            text-align: right;
            padding-right: 4mm;
        }
        .hoja-analisis__totales .pct {
            color: #64748b;
            font-size: 7.5pt;
        }
        .hoja-analisis__pu td,
        .hoja-analisis__total td {
            font-weight: 700;
            border-top: 1px solid #94a3b8;
        }
    </style>
</head>
<body>
@include('presupuestos.partials.presupuesto-pdf-hojas-analisis', [
    'hojasAnalisis' => $hojasAnalisis ?? [],
    'variant' => 'default',
    'monedaPrefijo' => $monedaPrefijo ?? '$',
    'saltoAntesPrimeraHoja' => false,
])
</body>
</html>
