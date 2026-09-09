@php
    use App\Services\Presupuesto\PresupuestoThemeService;
    use App\Support\PresupuestoPdf;

    $margenMm = 20;
    $footerHeightMm = 25.4;
    $atentamenteReserveMm = 30;
    $terminosLista = $presupuesto['terminos_enunciados'] ?? [];
    $validacionesLista = $presupuesto['validaciones_enunciados'] ?? [];
    $observacionesLista = $presupuesto['observaciones_enunciados'] ?? [];
    $anexosLista = $presupuesto['anexos'] ?? [];
    $mostrarAtentamente = PresupuestoPdf::debeMostrarBloqueAtentamenteDesdePayload($presupuesto);
    $tieneBloqueTerminos = count($terminosLista) > 0
        || count($validacionesLista) > 0
        || count($observacionesLista) > 0;
    $presupuestoThemeService = app(PresupuestoThemeService::class);
    $pdfThemeKey = $presupuestoThemeService->resolveThemeKey($presupuesto['pdf_theme'] ?? null);
    $presupuestoThemeCss = $presupuestoThemeService->generateCssVariables($pdfThemeKey);
    $presupuestoTableHeaderCss = $presupuestoThemeService->generateTableHeaderCss($pdfThemeKey);
    $thColors = $presupuestoThemeService->tableHeaderColors($pdfThemeKey);
    $thCellStyle = 'background-color:'.$thColors['bg'].';color:'.$thColors['text'].';border:1px solid '.$thColors['border'].';';
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Presupuesto {{ $presupuesto['numero_presupuesto'] ?? 'N/A' }}</title>
    {{--
      Plantilla PDF alternativa (utilidades estilo Tailwind embebidas).
      Acento corporativo azul #3498db (alineado con pdf.blade.php). DomPDF: sin CDN.
    --}}
<style>
    @page {
        size: letter;
        margin: 25.5mm;
    }

    {!! $presupuestoThemeCss !!}

    {!! $presupuestoTableHeaderCss !!}

    :root {
        /* =========================
           VARIABLES SEMÁNTICAS
        ========================== */

        --bg-body: var(--color-white);

        --text-primary: #111827;
        --text-secondary: #4b5563;
        --text-muted: #4b5563;
        --text-soft: #4b5563;

        --text-heading: var(--color-heading);

        --primary: var(--color-primary);
        --primary-dark: var(--color-primary-dark);
        --primary-soft: var(--color-primary-soft);
        --primary-border: var(--color-primary-border);

        --table-header-bg: var(--primary);
        --table-header-border: var(--primary-dark);
        --table-header-text: var(--color-white);

        --table-row-even-bg: var(--color-row-even);
        --table-row-odd-bg: var(--color-white);

        --paragraph-row-bg: var(--color-paragraph-bg);

        --border-soft: var(--color-slate-100);
        --border-default: var(--color-slate-200);

        --footer-accent: var(--primary);

        --importe-label-bg: var(--color-importe-label-bg);
        --importe-value-bg: var(--color-importe-value-bg);
    }

    html,
    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        font-size: 8.5pt;
        color: var(--text-primary);
        background: var(--bg-body);
        line-height: 1.2;
        margin: 0;
        padding: 0;
        padding-bottom: {{ $footerHeightMm }}mm;
    }

    body {
        padding-top: {{ $margenMm }}mm;
    }

    .margin-sides {
        padding-left: {{ $margenMm }}mm;
        padding-right: {{ $margenMm }}mm;
    }

    .document-container {
        width: 100%;
        background: var(--bg-body);
    }

    .document-main {
        min-height: calc(100vh - {{ $footerHeightMm + 42 }}mm);
        display: flex;
        flex-direction: column;
    }

    .document-main-spacer {
        flex: 1 1 auto;
        min-height: 2mm;
    }

    .document-main-spacer--atentamente {
        min-height: 28mm;
    }

    .document-closing {
        flex: 0 0 auto;
        width: 100%;
    }

    .document-closing-atentamente {
        flex: 0 0 auto;
        width: 100%;
        page-break-inside: avoid;
        page-break-after: avoid;
    }

    .terms-block--pagina-siguiente {
        page-break-before: always;
        page-break-inside: auto;
    }

    .terms-block--pagina-siguiente .tw-terms {
        page-break-inside: auto;
    }

    .terms-block--pagina-siguiente .tw-terms li {
        page-break-inside: auto;
    }

    .atentamente-plain {
        width: 100%;
        margin: 4mm 0 0 0;
        padding: 0 0 {{ max(14, (int) round($atentamenteReserveMm * 0.6)) }}mm 0;
        background: transparent;
        border: none;
        page-break-inside: avoid;
        max-width: 90mm;
    }

    .document-closing-atentamente .atentamente-plain {
        margin-top: 0;
        padding-top: 0;
    }

    .atentamente-plain .atentamente-spacer {
        height: 2.8mm;
        margin: 0;
        padding: 0;
        line-height: 0;
        font-size: 0;
    }

    .atentamente-plain .atentamente-title {
        margin-bottom: 0;
    }

    .atentamente-plain .tw-receptor-strong {
        margin-bottom: 0.35mm;
        line-height: 1.05;
    }

    .atentamente-plain .tw-receptor-line {
        margin-bottom: 0.2mm;
        line-height: 1.05;
    }

    .tw-header-wrap {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
        page-break-inside: avoid;
    }

    .tw-header-main {
        vertical-align: top;
        padding: 0;
        background: transparent;
    }

    .tw-header-rule {
        width: 100%;
        height: 0;
        margin: 3mm 0 3mm 0;
        padding: 0;
        border: 0;
        border-top: 3px solid var(--primary);
        font-size: 0;
        line-height: 0;
        overflow: hidden;
    }

    .tw-header-top {
        width: 100%;
        border-collapse: collapse;
    }

    .tw-logo-cell {
        box-sizing: border-box;
        padding: 0 0.7mm 0 0;
        vertical-align: top;
        text-align: left;
    }

    .tw-logo-box {
        overflow: hidden;
        display: block;
        box-sizing: border-box;
        min-width: 20mm;
        min-height: 20mm;
        max-width: 40mm;
        max-height: 30mm;
    }

    .tw-logo-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: left center;
        display: block;
    }

    .tw-logo-fallback {
        width: 100%;
        height: 100%;
        min-height: 20mm;
        background: var(--primary);
        border-radius: 1mm;
        text-align: center;
        line-height: normal;
        color: var(--color-white);
        font-size: 14pt;
        font-weight: bold;
        display: table;
    }

    .tw-logo-fallback span {
        display: table-cell;
        vertical-align: middle;
    }

    .tw-emisor-cell {
        vertical-align: top;
        padding-left: 0;
        width: auto;
    }

    .tw-emisor-name {
        font-size: 9.5pt;
        font-weight: 700;
        color: #111827;
        text-transform: uppercase;
        margin-bottom: 0.4mm;
        letter-spacing: 0.03em;
        line-height: var(--section-line-height);
    }

    .tw-emisor-line {
        font-size: 7pt;
        color: #111827;
        margin-bottom: 0.2mm;
        line-height: var(--section-line-height);
    }

    .tw-folio-cell {
        vertical-align: top;
        text-align: right;
        width: 30%;
    }

    .tw-badge-label {
        font-size: 6pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--primary);
        margin-bottom: 0.6mm;
    }

    .tw-badge-folio {
        display: block;
        background: transparent;
        border: none;
        color: var(--primary);
        font-size: 13pt;
        font-weight: 700;
        padding: 0;
        margin: 0 0 1mm 0;
        line-height: 1.1;
    }

    .tw-uuid {
        font-size: 6pt;
        color: var(--text-muted);
        word-break: break-all;
        max-width: 100%;
    }

    .tw-date {
        font-size: 7pt;
        color: var(--text-primary);
        margin-top: 0.6mm;
    }

    .tw-header-wrap--compact + .tw-header-rule {
        margin: 2mm 0 3mm 0;
    }

    .tw-header-wrap--compact .tw-logo-box {
        min-width: 0 !important;
        min-height: 0 !important;
    }

    .tw-header-wrap--compact .tw-emisor-name {
        font-size: 7.5pt;
        margin-bottom: 0.15mm;
    }

    .tw-header-wrap--compact .tw-emisor-line {
        font-size: 6pt;
        margin-bottom: 0.1mm;
    }

    .tw-header-wrap--compact .tw-badge-label {
        font-size: 5.5pt;
        margin-bottom: 0.25mm;
    }

    .tw-header-wrap--compact .tw-badge-folio {
        font-size: 10pt;
        margin: 0 0 0.4mm 0;
    }

    .tw-header-wrap--compact .tw-date {
        font-size: 6pt;
        margin-top: 0.25mm;
    }

    .tw-card,
    .tw-desc-box {
        width: 100%;
        margin-bottom: 3mm;
        padding-bottom: 2mm;
        padding-left: 0;
        padding-right: 0;
        padding-top: 0;
        page-break-inside: avoid;
    }

    .tw-card-title {
        font-size: 6pt;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--primary);
        margin: 0 0 1mm 0;
        padding: 0;
        border: none;
        line-height: 1.1;
    }

    .tw-card-title.tw-desc-general-title {
        letter-spacing: 0.5px;
    }

    .tw-receptor-strong {
        font-size: 9pt;
        font-weight: 700;
        color: #111827;
        margin-bottom: 1mm;
        line-height: 1.15;
    }

    .tw-receptor-line {
        font-size: 7pt;
        color: #111827;
        margin-bottom: 0.8mm;
        line-height: 1.15;
    }

    .tw-desc-text {
        font-size: 9pt;
        font-weight: 700;
        color: #111827;
        line-height: 1.15;
        text-align: left;
        white-space: pre-wrap;
    }

    .tw-section-title {
        font-size: 9.5pt;
        font-weight: 700;
        color: var(--text-heading);
        text-transform: none;
        letter-spacing: 0.02em;
        text-align: center;
        margin: 4mm 0 2mm 0;
        padding: 0;
        border: none;
        width: 100%;
    }

    .tw-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6mm;
        table-layout: fixed;
    }

    .tw-table thead {
        display: table-header-group;
    }

    .tw-table thead tr {
        background: var(--table-header-bg) !important;
        color: var(--table-header-text) !important;
    }

    .tw-table thead th {
        padding: 1.4mm 0.8mm;
        font-size: 6.2pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        text-align: center;
        line-height: 1.2;
        vertical-align: middle;
    }

    .tw-table thead th:nth-child(2) {
        text-align: left;
        padding-left: 1.5mm;
    }

    .tw-table thead th:nth-child(5),
    .tw-table thead th:nth-child(6) {
        text-align: right;
        padding-right: 1mm;
    }

    .tw-table tbody td {
        padding: 1.2mm 1mm;
        font-size: 6.5pt;
        border: 1px solid var(--border-default);
        vertical-align: top;
    }

    .tw-table tbody tr:nth-child(odd) {
        background: var(--table-row-odd-bg);
    }

    .tw-table tbody tr:nth-child(even) {
        background: var(--table-row-even-bg);
    }

    .tw-table tbody td:first-child {
        text-align: center;
        color: var(--text-muted);
        font-weight: 600;
    }

    .tw-table tbody td:nth-child(2) {
        text-align: left;
        color: #111827;
        padding-left: 1.2mm;
    }

    .tw-table tbody td:nth-child(3),
    .tw-table tbody td:nth-child(4) {
        text-align: center;
        color: var(--text-secondary);
        text-transform: uppercase;
    }

    .tw-table tbody td:nth-child(5),
    .tw-table tbody td:nth-child(6) {
        text-align: right;
        color: var(--text-primary);
    }

    .tw-table tbody td:nth-child(6) {
        font-weight: 600;
    }

    .tw-no-rows {
        text-align: center;
        font-style: italic;
        color: var(--text-soft);
        padding: 5mm !important;
    }

    .tw-table tbody tr.tw-linea-parrafo td {
        text-align: left;
        font-weight: 400;
        color: #111827;
        padding: 3mm 2.5mm;
        line-height: 1.45;
        white-space: pre-wrap;
        background: var(--paragraph-row-bg);
        max-height: 14mm;
        overflow: hidden;
    }

    .tw-totals-wrap {
        width: 100%;
        page-break-inside: avoid;
    }

    .tw-totals-inner {
        width: 52%;
        margin-left: 48%;
        border: 1px solid var(--border-default);
        border-radius: 1mm;
        overflow: hidden;
        background: var(--bg-body);
    }

    .tw-totals-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .tw-totals-table td {
        padding: 1mm 2mm;
        font-size: 7pt;
        white-space: nowrap;
        overflow: hidden;
    }

    .tw-totals-table td:first-child {
        width: 58%;
        text-align: right;
        color: var(--text-secondary);
        background: var(--color-slate-50);
    }

    .tw-totals-table .tw-totals-meta-value {
        text-align: right;
        font-weight: 600;
        color: var(--text-primary);
    }

    .tw-totals-table .tw-totals-money-sign-col {
        width: 12%;
        text-align: right;
        font-weight: 600;
        color: var(--text-muted);
        padding-right: 1mm;
    }

    .tw-totals-table .tw-totals-money-amount-col {
        width: 30%;
        text-align: right;
        font-weight: 600;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }

    .tw-totals-table .tw-total-row td {
        background: var(--bg-body);
        border-top: 2px solid var(--primary);
        font-size: 10pt;
        font-weight: 700;
    }

    .tw-totals-table .tw-total-row td:first-child {
        color: var(--text-heading);
        text-transform: uppercase;
    }

    .tw-totals-table .tw-total-row td:last-child {
        color: var(--primary);
    }

    .tw-totals-table .tw-total-row .tw-totals-money-sign-col,
    .tw-totals-table .tw-total-row .tw-totals-money-amount-col {
        color: inherit;
        font-weight: inherit;
    }

    .importe-con-letra {
        margin-top: 2mm;
        width: 100%;
        border: 1px solid var(--border-soft);
        border-radius: 1mm;
        background: var(--bg-body);
        page-break-inside: avoid;
        overflow: hidden;
    }

    .importe-con-letra-label {
        background: var(--importe-label-bg);
        text-align: center;
        font-size: 6pt;
        font-weight: 400;
        letter-spacing: 0.02em;
        text-transform: none;
        color: var(--text-soft);
        padding: 1mm 2mm;
        border-bottom: 1px solid var(--border-soft);
    }

    .importe-con-letra-valor {
        text-align: center;
        font-size: 6.5pt;
        font-weight: 400;
        color: #111827;
        padding: 1.8mm 2.5mm;
        line-height: 1.3;
        background: var(--importe-value-bg);
        white-space: normal;
        word-break: break-word;
    }

    .after-table-space {
        height: 8mm;
    }

    .after-table-space--compact {
        height: 3mm;
    }

    .tw-page-break {
        page-break-before: always;
    }

    .tw-anexos-page {
        width: 100%;
    }

    .tw-anexos-header {
        margin-bottom: 2.5mm;
        padding-bottom: 1.5mm;
        border-bottom: 1px solid var(--border-default);
    }

    .tw-anexos-title {
        font-size: 11pt;
        font-weight: 700;
        color: var(--text-heading);
        line-height: 1.15;
        margin: 0;
    }

    .tw-anexos-list {
        width: 100%;
    }

    .tw-anexo-simple {
        width: 100%;
        padding: 2.8mm 0;
        border-bottom: 1px solid var(--border-default);
        page-break-inside: avoid;
    }

    .tw-anexo-simple:last-child {
        border-bottom: none;
    }

    .tw-anexo-simple-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .tw-anexo-simple-media,
    .tw-anexo-simple-text {
        vertical-align: top;
    }

    .tw-anexo-simple-media {
        width: 52mm;
        padding-right: 3.5mm;
    }

    .tw-anexo-simple-image-wrap {
        height: 40mm;
        overflow: hidden;
        background: var(--color-slate-50);
        text-align: center;
    }

    .tw-anexo-simple-image {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
        margin: 0 auto;
    }

    .tw-anexo-simple-heading {
        font-size: 8.4pt;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
        margin-bottom: 1.1mm;
    }

    .tw-anexo-simple-desc {
        font-size: 7.1pt;
        color: #111827;
        line-height: 1.3;
        word-break: break-word;
        white-space: pre-wrap;
        margin-bottom: 1.1mm;
    }

    .tw-anexo-simple-price {
        font-size: 7.8pt;
        font-weight: 700;
        color: var(--primary);
    }

    .terms-block {
        margin-bottom: 3mm;
        page-break-inside: auto;
    }

    .tw-terms + .tw-terms {
        margin-top: 2mm;
    }

    .tw-terms {
        margin-top: 3mm;
        padding-top: 0;
        border-top: none;
    }

    .tw-terms h3 {
        font-size: 6.5pt;
        font-weight: 700;
        color: var(--text-heading);
        text-transform: none;
        letter-spacing: 0.02em;
        margin: 0 0 1mm 0;
        line-height: var(--section-line-height);
    }

    .tw-terms ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .tw-terms ul.tw-terms-num {
        counter-reset: twi;
    }

    .tw-terms ul.tw-terms-num li {
        font-size: 6.2pt;
        color: #111827;
        line-height: var(--section-line-height);
        margin-bottom: 0.6mm;
        padding-left: 5mm;
        position: relative;
        text-align: justify;
    }

    .tw-terms ul.tw-terms-num li::before {
        counter-increment: twi;
        content: counter(twi) ".";
        position: absolute;
        left: 0;
        font-weight: 600;
        color: var(--text-primary);
    }

    .tw-terms ul.tw-obs-list {
        counter-reset: twobs;
    }

    .tw-terms ul.tw-obs-list li {
        font-size: 6.2pt;
        color: #111827;
        line-height: var(--section-line-height);
        margin-bottom: 0.6mm;
        padding-left: 5mm;
        position: relative;
        text-align: justify;
    }

    .tw-terms ul.tw-obs-list li::before {
        counter-increment: twobs;
        content: counter(twobs) ".";
        position: absolute;
        left: 0;
        font-weight: 600;
        color: var(--text-primary);
    }

    .footer {
        position: fixed;
        bottom: 6mm;
        left: {{ $margenMm }}mm;
        right: {{ $margenMm }}mm;
        height: {{ $footerHeightMm - 2 }}mm;
        min-height: {{ $footerHeightMm - 2 }}mm;
        padding: 1mm 0 2mm;
        font-size: 6.5pt;
        color: #111827;
        line-height: 1.3;
        overflow: visible;
    }

    .footer-table {
        display: table;
        width: 100%;
    }

    .footer-left {
        display: table-cell;
        width: 33%;
        vertical-align: bottom;
    }

    .footer-center {
        display: table-cell;
        width: 34%;
        text-align: center;
        vertical-align: middle;
    }

    .footer-right {
        display: table-cell;
        width: 33%;
        text-align: right;
        vertical-align: middle;
        padding-left: 2mm;
    }

    .footer-logos-row {
        display: table;
    }

    .footer-logo-cell {
        display: table-cell;
        padding-right: 2.5mm;
        vertical-align: middle;
    }

    .footer-logo-cell:last-child {
        padding-right: 0;
    }

    .footer-logo-img {
        width: 12mm;
        height: 12mm;
        object-fit: contain;
        display: block;
    }

    .footer-logo-placeholder {
        width: 12mm;
        height: 12mm;
        min-width: 12mm;
        background: var(--border-default);
        border-radius: 1.5mm;
        font-size: 5pt;
        font-weight: 700;
        color: var(--text-muted);
        text-align: center;
        line-height: 8mm;
    }

    .footer-center-content {
        text-align: center;
        width: 100%;
    }

    .footer-pages {
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 7pt;
        margin-bottom: 0.6mm;
        min-height: 3mm;
    }

    .footer-slogan {
        font-style: italic;
        color: var(--footer-accent);
        font-size: 6pt;
    }

    .footer-webs {
        font-size: 6pt;
    }

    .footer-webs-link {
        color: var(--footer-accent);
        text-decoration: none;
    }

    .footer-webs-sep {
        color: var(--text-soft);
        margin: 0 1mm;
    }

    .footer-qr {
        display: inline-block;
        width: 15mm;
        height: 15mm;
        vertical-align: middle;
    }

    .footer-qr img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
</style>
</head>

<body>
    <div class="footer">
        <div class="footer-table">
            <div class="footer-left">
                @php
                    $logos = $presupuesto['logos_base64'] ?? [];
                    $appKeys = ['gestionplus'];
                @endphp
                <div class="footer-logos-row">
                    @foreach ($appKeys as $key)
                        <div class="footer-logo-cell">
                            @if (!empty($logos[$key]))
                                <img src="{{ $logos[$key] }}" alt="" class="footer-logo-img" />
                            @else
                                <span class="footer-logo-placeholder">{{ strtoupper(substr($key, 0, 1)) }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="footer-center">
                <div class="footer-center-content">
                    <div class="footer-slogan">"Calidad y compromiso en cada proyecto"</div>
                    <div class="footer-webs">
                        <a href="https://heventec.com" class="footer-webs-link" target="_blank">heventec.com</a><span
                            class="footer-webs-sep">|</span><a href="https://gestion.heventec.com/" target="_blank"
                            class="footer-webs-link">gestion.heventec.com</a>
                        <div class="footer-pages">&nbsp;</div>
                    </div>
                </div>
            </div>
            <div class="footer-right">
                @if (isset($presupuesto['qr_code']) && $presupuesto['qr_code'])
                    <div class="footer-qr">
                        <img src="{{ $presupuesto['qr_code'] }}" alt="Ver versión web" title="Ver versión web" />
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="margin-sides">
        <div class="document-container document-main{{ $mostrarAtentamente ? ' document-main--with-atentamente-footer' : '' }}">
            @include('presupuestos.partials.presupuesto-pdf-header-tailwind')

            <div class="tw-card">
                <div class="tw-card-title">Dirigido a:</div>
                @foreach ($presupuesto['receptor_lineas'] ?? [] as $idx => $linea)
                    <div class="{{ $idx === 0 ? 'tw-receptor-strong' : 'tw-receptor-line' }}">{{ $linea }}</div>
                @endforeach
            </div>

            @if ($presupuesto['concepto_general'] ?? null)
                <div class="tw-desc-box">
                    <div class="tw-card-title tw-desc-general-title">Descripción general</div>
                    <div class="tw-desc-text">{{ $presupuesto['concepto_general'] }}</div>
                </div>
            @endif

            <div class="tw-section-title">Presupuesto</div>
            <table class="tw-table">
                <thead>
                    <tr>
                        <th scope="col" style="width:5%;{{ $thCellStyle }}">#</th>
                        <th scope="col" style="width:36%;{{ $thCellStyle }}">Descripción</th>
                        <th scope="col" style="width:10%;{{ $thCellStyle }}">Cantidad</th>
                        <th scope="col" style="width:10%;{{ $thCellStyle }}">Unidad</th>
                        <th scope="col" style="width:19%;{{ $thCellStyle }}">Precio unitario</th>
                        <th scope="col" style="width:20%;{{ $thCellStyle }}">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $conceptos = $presupuesto['conceptos'] ?? [];
                        $subtotal = 0;
                    @endphp
                    @if (count($conceptos) > 0)
                        @foreach ($conceptos as $index => $concepto)
                            @php
                                $tipoLinea = $concepto['tipo'] ?? 'concepto';
                                $esParrafo = $tipoLinea === 'parrafo';
                                $cantidad = $concepto['cantidad'] ?? 1;
                                $precioUnitario = $concepto['precio_unitario'] ?? 0;
                                $importe = $esParrafo ? 0 : $cantidad * $precioUnitario;
                                $subtotal += $importe;
                            @endphp
                            @if ($esParrafo)
                                <tr class="tw-linea-parrafo">
                                    <td>{{ $index + 1 }}</td>
                                    <td colspan="5">{{ $concepto['descripcion'] ?? '' }}</td>
                                </tr>
                            @else
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $concepto['descripcion'] ?? 'Sin descripción' }}</td>
                                <td>{{ number_format($cantidad, 2, '.', ',') }}</td>
                                <td>{{ strtoupper($concepto['unidad'] ?? 'PZA') }}</td>
                                <td>${{ number_format($precioUnitario, 2, '.', ',') }}</td>
                                <td>${{ number_format($importe, 2, '.', ',') }}</td>
                            </tr>
                            @endif
                        @endforeach
                    @else
                        <tr>
                            <td colspan="6" class="tw-no-rows">No hay conceptos registrados</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            @if ($presupuesto['config_mostrar_totales'] ?? true)
            <div class="tw-totals-wrap">
                @php
                    $subtotalCalculado = (float) ($presupuesto['subtotal'] ?? $subtotal);
                    $conIva = (bool) ($presupuesto['con_iva'] ?? false);
                    $ivaPorcentaje = (float) ($presupuesto['iva_porcentaje'] ?? 16);
                    $pctDescuento = array_key_exists('porcentaje_descuento', $presupuesto)
                        ? ($presupuesto['porcentaje_descuento'] !== null ? (int) $presupuesto['porcentaje_descuento'] : null)
                        : null;
                    $cantidadDescuento = array_key_exists('cantidad_descuento', $presupuesto)
                        ? ($presupuesto['cantidad_descuento'] !== null ? (float) $presupuesto['cantidad_descuento'] : null)
                        : null;
                    $totalesDoc = \App\Models\Presupuesto::calcularTotalesDocumento(
                        $subtotalCalculado,
                        $pctDescuento,
                        $cantidadDescuento,
                        $conIva,
                        $ivaPorcentaje
                    );
                    $ivaTotal = $totalesDoc['iva_total'];
                    $total = $totalesDoc['total'];
                    $monedaCodigo = strtoupper((string) ($presupuesto['term_cond_moneda'] ?? 'MXN'));
                    if (!in_array($monedaCodigo, ['MXN', 'USD', 'EUR'], true)) {
                        $monedaCodigo = 'MXN';
                    }
                    $monedaPrefijo = $monedaCodigo === 'EUR' ? '€' : '$';
                @endphp
                <div class="tw-totals-inner">
                    <table class="tw-totals-table">
                        <tr>
                            <td>Subtotal</td>
                            <td class="tw-totals-money-sign-col">{{ $monedaPrefijo }}</td>
                            <td class="tw-totals-money-amount-col">{{ number_format($totalesDoc['subtotal'], 2, '.', ',') }}</td>
                        </tr>
                        @if ($totalesDoc['mostrar_descuento'])
                            <tr>
                                <td>Descuento ({{ $totalesDoc['porcentaje_descuento'] }}%)</td>
                                <td class="tw-totals-money-sign-col">- {{ $monedaPrefijo }}</td>
                                <td class="tw-totals-money-amount-col">{{ number_format($totalesDoc['monto_descuento'], 2, '.', ',') }}</td>
                            </tr>
                        @endif
                        @if ($conIva)
                            <tr>
                                <td>IVA ({{ number_format($ivaPorcentaje, 0) }}%)</td>
                                <td class="tw-totals-money-sign-col">{{ $monedaPrefijo }}</td>
                                <td class="tw-totals-money-amount-col">{{ number_format($ivaTotal, 2, '.', ',') }}</td>
                            </tr>
                        @endif
                        <tr class="tw-total-row">
                            <td>Total</td>
                            <td class="tw-totals-money-sign-col">{{ $monedaPrefijo }}</td>
                            <td class="tw-totals-money-amount-col">{{ number_format($total, 2, '.', ',') }}</td>
                        </tr>
                    </table>
                    <div class="importe-con-letra">
                        <div class="importe-con-letra-label">Importe con letra:</div>
                        <div class="importe-con-letra-valor">
                            {{ \App\Support\PresupuestoPdf::formatMontoLegal($total, $monedaCodigo) }}
                        </div>
                    </div>
                </div>
                <div class="after-table-space after-table-space--compact"></div>
            </div>
            @endif

            @if ($mostrarAtentamente)
                <div class="document-main-spacer document-main-spacer--atentamente"></div>
                <div class="document-closing-atentamente">
                    @include('presupuestos.partials.presupuesto-pdf-atentamente', ['variant' => 'tailwind'])
                </div>
            @else
                <div class="document-main-spacer"></div>
                <div class="document-closing">
                    <div class="terms-block">
                        @if (count($terminosLista) > 0)
                            <div class="tw-terms">
                                <h3>Términos y Condiciones</h3>
                                <ul class="tw-terms-num">
                                    @foreach ($terminosLista as $texto)
                                        <li>{{ $texto }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (count($observacionesLista) > 0)
                            <div class="tw-terms">
                                <h3>Observaciones</h3>
                                <ul class="tw-obs-list">
                                    @foreach ($observacionesLista as $obs)
                                        <li>{{ $obs }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        @if ($mostrarAtentamente && $tieneBloqueTerminos)
            <div class="terms-block terms-block--pagina-siguiente">
                @if (count($terminosLista) > 0)
                    <div class="tw-terms">
                        <h3>Términos y Condiciones</h3>
                        <ul class="tw-terms-num">
                            @foreach ($terminosLista as $texto)
                                <li>{{ $texto }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (count($observacionesLista) > 0)
                    <div class="tw-terms">
                        <h3>Observaciones</h3>
                        <ul class="tw-obs-list">
                            @foreach ($observacionesLista as $obs)
                                <li>{{ $obs }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

            @if (count($anexosLista) > 0)
                @foreach (collect($anexosLista)->chunk(4) as $pageIndex => $anexosPagina)
                    <div class="tw-page-break"></div>
                    <div class="tw-anexos-page">
                        @include('presupuestos.partials.presupuesto-pdf-header-tailwind', ['headerCompact' => true])
                        <div class="tw-anexos-header">
                            <div class="tw-anexos-title">Anexos</div>
                        </div>

                        <div class="tw-anexos-list">
                            @foreach ($anexosPagina as $index => $anexo)
                                @php
                                    $numeroAnexo = (($pageIndex * 4) + $index + 1);
                                @endphp
                                <div class="tw-anexo-simple">
                                    <table class="tw-anexo-simple-table">
                                        <tr>
                                            @if (!empty($anexo['archivo_base64']))
                                                <td class="tw-anexo-simple-media">
                                                    <div class="tw-anexo-simple-image-wrap">
                                                        <img src="{{ $anexo['archivo_base64'] }}" alt="{{ $anexo['titulo'] ?? ('Anexo ' . (($anexo['orden'] ?? 0) ?: $numeroAnexo)) }}" class="tw-anexo-simple-image" />
                                                    </div>
                                                </td>
                                            @endif
                                            <td class="tw-anexo-simple-text">
                                                <div class="tw-anexo-simple-heading">{{ $anexo['titulo'] ?? '' }}</div>
                                                @if (!empty($anexo['descripcion']))
                                                    <div class="tw-anexo-simple-desc">{{ $anexo['descripcion'] }}</div>
                                                @endif
                                                @if (array_key_exists('precio', $anexo) && $anexo['precio'] !== null)
                                                    <div class="tw-anexo-simple-price">${{ number_format((float) $anexo['precio'], 2, '.', ',') }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <script type="text/php">
        if (isset($pdf) && isset($fontMetrics)) {
            $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
            $size = 7;
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            $sample = "Página 99 de 99";
            $width = $fontMetrics->getTextWidth($sample, $font, $size);
            $x = ($pdf->get_width() - $width) / 2 + 5;
            $y = $pdf->get_height() - 45;
            $pdf->page_text($x, $y, $text, $font, $size);
        }
    </script>
</body>

</html>
