@php
    $margenPaginaMm = \App\Support\PresupuestoPdfDocumentConfig::MARGEN_HOJA_MM;
    $margenMm = 20.0;
    $lineaMm = \App\Support\PresupuestoPdfDocumentConfig::LINEA_MM;
    $margenSuperiorMm = max(8.0, $margenMm - (4 * $lineaMm));
    $footerHeightMm = \App\Support\PresupuestoPdfDocumentConfig::FOOTER_HEIGHT_MM;
    $footerBottomMm = \App\Support\PresupuestoPdfDocumentConfig::FOOTER_BOTTOM_MM;
    $bodyPaddingBottomMm = $footerHeightMm + \App\Support\PresupuestoPdfDocumentConfig::BODY_PADDING_BOTTOM_EXTRA_MM;
    $inicial = mb_strtoupper(mb_substr($empresaNombre ?: 'E', 0, 1));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización {{ $solicitud->folio }}</title>
    <style>
        @page {
            size: letter;
            margin: {{ $margenPaginaMm }}mm;
        }

        html, body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 8pt;
            color: #111827;
            background: #fff;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            padding-bottom: {{ $bodyPaddingBottomMm }}mm;
        }

        body { padding-top: {{ $margenSuperiorMm }}mm; }

        .margin-sides {
            padding-left: {{ $margenMm }}mm;
            padding-right: {{ $margenMm }}mm;
        }

        /* ===== Secciones definidas (estilo comprobante) ===== */
        .section {
            width: 100%;
            border: 1px solid #1f2937;
            margin-bottom: 3mm;
            page-break-inside: avoid;
            box-sizing: border-box;
        }

        .section-head {
            background: #1f2937;
            color: #ffffff;
            font-size: 6.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 1.4mm 2.5mm;
        }

        .section-body {
            padding: 2.5mm;
        }

        .grid-2 {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .grid-2 > tbody > tr > td {
            vertical-align: top;
        }

        .field-label {
            font-size: 6pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            margin: 0 0 0.5mm 0;
        }

        .field-value {
            font-size: 8pt;
            font-weight: 600;
            color: #111827;
            margin: 0 0 1.6mm 0;
            line-height: 1.2;
        }

        .field-value.lg {
            font-size: 10pt;
            font-weight: 700;
        }

        .field-value.sm {
            font-size: 7pt;
            font-weight: 500;
            color: #374151;
        }

        /* Header emisor + folio/QR */
        .top-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 3mm;
            page-break-inside: avoid;
        }

        .top-grid > tbody > tr > td {
            vertical-align: top;
        }

        .box-emisor {
            width: 62%;
            border: 1px solid #1f2937;
            padding: 0;
        }

        .box-folio {
            width: 38%;
            border: 1px solid #1f2937;
            border-left: 0;
            padding: 0;
        }

        .logo-img {
            max-width: 24mm;
            max-height: 12mm;
            object-fit: contain;
        }

        .logo-fallback {
            width: 11mm;
            height: 11mm;
            background: #1f2937;
            color: #fff;
            font-size: 9pt;
            font-weight: 700;
            text-align: center;
            line-height: 11mm;
        }

        .empresa-nombre {
            font-size: 9.5pt;
            font-weight: 700;
            text-transform: uppercase;
            margin: 0 0 1mm 0;
            letter-spacing: 0.02em;
        }

        .empresa-linea {
            font-size: 7pt;
            color: #374151;
            margin: 0 0 0.4mm 0;
        }

        .qr-img {
            width: 22mm;
            height: 22mm;
        }

        .folio-big {
            font-size: 12pt;
            font-weight: 700;
            margin: 0 0 1.5mm 0;
            letter-spacing: 0.02em;
        }

        .codigo-verif {
            font-size: 6pt;
            color: #4b5563;
            word-break: break-all;
            line-height: 1.25;
            margin-top: 1mm;
        }

        /* Tabla conceptos */
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }

        .items th {
            background: #1f2937;
            color: #fff;
            font-size: 6.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1.6mm 1.2mm;
            border: 1px solid #111827;
            text-align: left;
        }

        .items td {
            font-size: 7.5pt;
            color: #1f2937;
            padding: 1.6mm 1.2mm;
            border: 1px solid #9ca3af;
            vertical-align: top;
        }

        .items tr:nth-child(even) td { background: #f9fafb; }

        .c { text-align: center; }
        .r { text-align: right; }

        .desc-name { font-weight: 700; margin: 0 0 0.3mm 0; }
        .desc-meta { font-size: 6.5pt; color: #6b7280; margin: 0; }

        /* Totales + QR inferior */
        .totales-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .totales-grid > tbody > tr > td {
            vertical-align: top;
            border: 1px solid #1f2937;
        }

        .box-qr-footer {
            width: 28%;
            padding: 2mm;
            text-align: center;
        }

        .box-letra {
            width: 36%;
            padding: 2.5mm;
        }

        .box-totales {
            width: 36%;
            padding: 0;
        }

        .totales {
            width: 100%;
            border-collapse: collapse;
        }

        .totales td {
            padding: 1.4mm 2mm;
            border-bottom: 1px solid #d1d5db;
            font-size: 7.5pt;
        }

        .totales tr:last-child td { border-bottom: 0; }

        .totales .lbl {
            text-align: left;
            color: #4b5563;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 6.5pt;
            letter-spacing: 0.04em;
        }

        .totales .amt {
            text-align: right;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .totales .total-row td {
            background: #1f2937;
            color: #fff;
            font-size: 9pt;
        }

        .letra-valor {
            font-size: 7pt;
            color: #374151;
            line-height: 1.3;
            margin-top: 1mm;
        }

        .qr-caption {
            font-size: 5.8pt;
            color: #6b7280;
            margin-top: 1mm;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .condiciones li {
            margin-bottom: 0.6mm;
            font-size: 7pt;
            color: #374151;
        }

        .condiciones {
            margin: 0;
            padding-left: 4mm;
        }

        .disclaimer {
            font-size: 6pt;
            color: #6b7280;
            margin-top: 2mm;
            text-align: center;
        }

        /* Footer fijo */
        .footer {
            position: fixed;
            bottom: {{ $footerBottomMm }}mm;
            left: {{ $margenMm }}mm;
            right: {{ $margenMm }}mm;
            height: {{ $footerHeightMm - 4 }}mm;
            border-top: 1px solid #9ca3af;
            padding-top: 1.5mm;
            font-size: 6.5pt;
            color: #6b7280;
        }

        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { vertical-align: middle; }
        .footer-left { width: 40%; }
        .footer-center { width: 20%; text-align: center; }
        .footer-right { width: 40%; text-align: right; }
        .footer-logo { max-height: 6mm; max-width: 16mm; }
        .footer-strong { font-weight: 700; color: #374151; }
    </style>
</head>
<body>
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    <span class="footer-strong">{{ $empresaNombre }}</span><br>
                    Cotización {{ $solicitud->folio }}
                </td>
                <td class="footer-center">
                    @if($logoNexprov)
                        <img class="footer-logo" src="{{ $logoNexprov }}" alt="NexProv">
                    @endif
                </td>
                <td class="footer-right">&nbsp;</td>
            </tr>
        </table>
    </div>

    <div class="margin-sides">

        {{-- SECCIÓN 1: Emisor + Folio/QR --}}
        <table class="top-grid">
            <tr>
                <td class="box-emisor">
                    <div class="section-head">Emisor</div>
                    <div class="section-body">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:26mm; vertical-align:top;">
                                    @if($logoEmpresa)
                                        <img class="logo-img" src="{{ $logoEmpresa }}" alt="Logo">
                                    @else
                                        <div class="logo-fallback">{{ $inicial }}</div>
                                    @endif
                                </td>
                                <td style="vertical-align:top;">
                                    <div class="empresa-nombre">{{ $empresaNombre }}</div>
                                    @if(!empty($empresaRazonSocial) && strcasecmp($empresaRazonSocial, $empresaNombre) !== 0)
                                        <div class="empresa-linea">{{ $empresaRazonSocial }}</div>
                                    @endif
                                    @if(!empty($empresaRfc))
                                        <div class="empresa-linea"><strong>RFC:</strong> {{ $empresaRfc }}</div>
                                    @endif
                                    @if(!empty($empresaDireccion))
                                        <div class="empresa-linea">{{ $empresaDireccion }}</div>
                                    @endif
                                    @if(!empty($empresaCiudadEstado))
                                        <div class="empresa-linea">{{ $empresaCiudadEstado }}</div>
                                    @endif
                                    @if(!empty($empresaTelefono) || !empty($empresaEmail))
                                        <div class="empresa-linea">
                                            @if(!empty($empresaTelefono))Tel. {{ $empresaTelefono }}@endif
                                            @if(!empty($empresaTelefono) && !empty($empresaEmail)) · @endif
                                            @if(!empty($empresaEmail)){{ $empresaEmail }}@endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td class="box-folio">
                    <div class="section-head">Documento</div>
                    <div class="section-body">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="vertical-align:top; width:58%;">
                                    <div class="field-label">Cotización</div>
                                    <div class="folio-big">{{ $solicitud->folio }}</div>
                                    <div class="field-label">Fecha</div>
                                    <div class="field-value">{{ $fechaLarga }}</div>
                                    <div class="field-label">Vigente al</div>
                                    <div class="field-value">{{ $vigencia }}</div>
                                </td>
                                <td style="vertical-align:top; text-align:center; width:42%;">
                                    @if(!empty($qrCode))
                                        <img class="qr-img" src="{{ $qrCode }}" alt="QR">
                                        <div class="qr-caption">Verificación</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <div class="codigo-verif">
                            <strong>Código:</strong> {{ $codigoVerificacion }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- SECCIÓN 2: Cliente --}}
        <div class="section">
            <div class="section-head">Cliente / Receptor</div>
            <div class="section-body">
                <table class="grid-2">
                    <tr>
                        <td style="width:55%; padding-right:3mm;">
                            <div class="field-label">Nombre</div>
                            <div class="field-value lg">{{ $solicitud->cliente_nombre }}</div>
                            @if($solicitud->cliente_email)
                                <div class="field-label">Correo</div>
                                <div class="field-value">{{ $solicitud->cliente_email }}</div>
                            @endif
                        </td>
                        <td style="width:45%;">
                            @if($solicitud->cliente_telefono)
                                <div class="field-label">Teléfono</div>
                                <div class="field-value">{{ $solicitud->cliente_telefono }}</div>
                            @endif
                            @if($solicitud->cliente_whatsapp)
                                <div class="field-label">WhatsApp</div>
                                <div class="field-value">{{ $solicitud->cliente_whatsapp }}</div>
                            @endif
                            <div class="field-label">Estatus</div>
                            <div class="field-value" style="text-transform:uppercase;">{{ $solicitud->estatus }}</div>
                        </td>
                    </tr>
                </table>
                @if($solicitud->cliente_notas)
                    <div class="field-label">Notas</div>
                    <div class="field-value sm">{{ $solicitud->cliente_notas }}</div>
                @endif
            </div>
        </div>

        {{-- SECCIÓN 3: Conceptos --}}
        <div class="section" style="page-break-inside:auto;">
            <div class="section-head">Conceptos / Productos</div>
            <table class="items">
                <thead>
                    <tr>
                        <th class="c" style="width:6%;">#</th>
                        <th class="c" style="width:10%;">Cant.</th>
                        <th style="width:10%;">Unidad</th>
                        <th style="width:42%;">Descripción</th>
                        <th class="r" style="width:16%;">P. unitario</th>
                        <th class="r" style="width:16%;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detalles as $i => $detalle)
                        <tr>
                            <td class="c">{{ $i + 1 }}</td>
                            <td class="c">{{ rtrim(rtrim(number_format((float) $detalle->cantidad, 3, '.', ''), '0'), '.') }}</td>
                            <td class="c">{{ $detalle->unidad ?: '—' }}</td>
                            <td>
                                <div class="desc-name">{{ $detalle->nombre }}</div>
                                @if($detalle->codigo)
                                    <div class="desc-meta">Clave: {{ $detalle->codigo }}</div>
                                @endif
                                @if($detalle->es_sugerencia_empresa)
                                    <div class="desc-meta">* Sugerencia empresa{{ $detalle->motivo_sugerencia ? ': '.$detalle->motivo_sugerencia : '' }}</div>
                                @endif
                                @if($detalle->observaciones_linea)
                                    <div class="desc-meta">{{ $detalle->observaciones_linea }}</div>
                                @endif
                            </td>
                            <td class="r">$ {{ number_format((float) $detalle->precio_unitario, 2, '.', ',') }}</td>
                            <td class="r">$ {{ number_format((float) $detalle->subtotal, 2, '.', ',') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- SECCIÓN 4: Totales + QR + importe con letra --}}
        <table class="totales-grid" style="margin-bottom:3mm;">
            <tr>
                <td class="box-qr-footer">
                    @if(!empty($qrCode))
                        <img class="qr-img" src="{{ $qrCode }}" alt="QR">
                        <div class="qr-caption">Escanee para verificar</div>
                    @endif
                    <div class="codigo-verif" style="text-align:left; margin-top:1.5mm;">
                        <strong>ID:</strong> {{ $solicitud->id }}<br>
                        <strong>Folio:</strong> {{ $solicitud->folio }}
                    </div>
                </td>
                <td class="box-letra">
                    <div class="field-label">Importe con letra</div>
                    <div class="letra-valor">{{ $importeConLetra }}</div>
                    <div class="field-label" style="margin-top:2.5mm;">Moneda</div>
                    <div class="field-value">MXN — Moneda nacional</div>
                </td>
                <td class="box-totales">
                    <table class="totales">
                        <tr>
                            <td class="lbl">Líneas</td>
                            <td class="amt">{{ count($detalles) }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Subtotal</td>
                            <td class="amt">$ {{ number_format((float) $total, 2, '.', ',') }}</td>
                        </tr>
                        <tr class="total-row">
                            <td class="lbl">Total</td>
                            <td class="amt">$ {{ number_format((float) $total, 2, '.', ',') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- SECCIÓN 5: Condiciones --}}
        <div class="section">
            <div class="section-head">Condiciones</div>
            <div class="section-body">
                <ul class="condiciones">
                    @foreach(($politicasLista ?? []) as $politica)
                        <li>{{ $politica }}</li>
                    @endforeach
                    <li>Vigencia de la cotización: {{ $vigencia }}.</li>
                    <li>Documento generado con NexProv a partir de la solicitud {{ $solicitud->folio }}.</li>
                </ul>
                <div class="disclaimer">
                    Este documento es la representación impresa de una cotización NexProv.<br>
                    Código de verificación: {{ $codigoVerificacion }}
                </div>
            </div>
        </div>

    </div>

    <script type="text/php">
        if (isset($pdf) && isset($fontMetrics)) {
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $size = 7;
            $sample = 'Página 99 de 99';
            $width = $fontMetrics->getTextWidth($sample, $font, $size);
            $x = ($pdf->get_width() - $width) / 2;
            $pdf->page_text($x, $pdf->get_height() - 45, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, $size, array(0.42, 0.45, 0.50));
        }
    </script>
</body>
</html>
