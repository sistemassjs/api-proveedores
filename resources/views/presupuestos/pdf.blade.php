        @php
            /** @var array<string, mixed> $presupuesto */
            /** @var \App\Support\PresupuestoPdfDocumentConfig|null $pdf */
            $pdf = $pdf ?? \App\Support\PresupuestoPdfDocumentConfig::fromPresupuestoPayload($presupuesto);
            extract($pdf->bladeViewVariables($presupuesto), EXTR_SKIP);
        @endphp
        <!DOCTYPE html>
        <html lang="es">

        <head>
            <meta charset="UTF-8">
            <title>Presupuesto {{ $presupuesto['numero_presupuesto'] ?? 'N/A' }}</title>
            <style>
                :root {
                    --accent: #3498db;
                    --accent-dark: #2980b9;
                    --accent-muted: #5dade2;
                    --text-heading: #2c3e50;
                    --text-body: #34495e;
                    --border-doc: #d1d5db;
                }

                @page {
                    size: letter;
                    margin: {{ $margenPaginaMm }}mm;
                }

                .page-top-spacing {
                    padding-top: {{ $margenSuperiorMm }}mm;
                }

                .content-wrapper {
                    min-height: calc(100vh - {{ $footerHeightMm + 20 }}mm);
                    display: flex;
                    flex-direction: column;
                }

                html,
                body {
                    font-family: 'DejaVu Sans', Arial, sans-serif;
                    font-size: 8.5pt;
                    color: #171a1d;
                    background: #ffffff;
                    line-height: 1.15;
                    margin: 0;
                    padding: 0;
                    padding-bottom: {{ $bodyPaddingBottomMm }}mm;
                    /* 🔥 clave */
                }

                body {
                    font-family: 'DejaVu Sans', Arial, sans-serif;
                    font-size: 8.5pt;
                    color: #171a1d;
                    background: #ffffff;
                    line-height: 1.15;
                    /* margin: 0; */
                    padding-top: {{ $margenSuperiorMm }}mm;
                }

                /* Elementos de margen (cuando @page margin no funciona) */


                .margin-sides {
                    padding-left: {{ $margenMm }}mm;
                    padding-right: {{ $margenMm }}mm;
                }

                .document-container {
                    width: 100%;
                    max-width: 100%;
                    margin: 0;
                    padding: 0;
                    background: #ffffff;
                    overflow-x: hidden;
                }

                .document-main {
                    margin-bottom: 0;
                    display: block;
                }

                .pdf-seccion--presupuesto {
                    width: 100%;
                }

                .presupuesto-reserva-atentamente-pie {
                    display: block;
                    width: 100%;
                    box-sizing: border-box;
                    page-break-inside: avoid;
                    page-break-after: avoid;
                    margin: 0;
                    padding: 0;
                    min-height: 0;
                }

                .pdf-seccion-presupuesto__atentamente {
                    margin-top: 0;
                    margin-bottom: {{ $gapAtentamenteFooterMm }}mm;
                    page-break-inside: auto;
                    page-break-before: avoid;
                }

                .pdf-seccion--anexos,
                .pdf-seccion--documentacion {
                    page-break-before: auto;
                    width: 100%;
                }

                .pdf-seccion-documentacion__pagina {
                    width: 100%;
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
                    page-break-inside: auto;
                    page-break-before: avoid;
                    page-break-after: avoid;
                }

                .presupuesto-cierre-terminos-atentamente {
                    width: 100%;
                }

                .terms-block--after-presupuesto {
                    flex: 0 0 auto;
                    width: 100%;
                    margin-bottom: 2mm;
                    page-break-inside: auto;
                }

                .terms-block--after-presupuesto .terminos-section:first-child {
                    margin-top: 2mm;
                    padding-top: 1mm;
                }

                .terms-block--after-presupuesto .terminos-section {
                    page-break-inside: auto;
                }

                .terms-block--after-presupuesto .terminos-list li,
                .terms-block--after-presupuesto .observaciones-list li {
                    page-break-inside: avoid;
                    break-inside: avoid;
                }

                .atentamente-plain {
                    width: 100%;
                    margin: 0;
                    padding: 0 0 {{ $gapAtentamenteFooterMm }}mm 0;
                    background: transparent;
                    border: none;
                    page-break-inside: auto;
                    max-width: 90mm;
                }

                .document-closing-atentamente .atentamente-plain {
                    margin-top: 0;
                    padding-top: 0;
                }

                .atentamente-plain .atentamente-spacer {
                    height: {{ $espacioTrasTituloAtentamenteMm }}mm;
                    margin: 0;
                    padding: 0;
                    line-height: 0;
                    font-size: 0;
                }

                .atentamente-plain .atentamente-title {
                    margin-bottom: 0;
                }

                .atentamente-plain .receptor-name {
                    margin-bottom: 0.35mm;
                    line-height: 1.05;
                }

                .atentamente-plain .receptor-info {
                    margin-bottom: 0.2mm;
                    line-height: 1.05;
                }


                /* ========== 1) ENCABEZADO ========== */
                .header {
                    margin-bottom: 0;
                    padding-bottom: 2mm;
                    border-bottom: none;
                    page-break-inside: avoid;
                }

                /* Misma línea que en encabezado compacto (hojas 2+) */
                .header-rule {
                    width: 100%;
                    height: 0;
                    margin: {{ $gapHeaderRuleMm ?? 3 }}mm 0 {{ $gapHeaderRuleMm ?? 3 }}mm 0;
                    padding: 0;
                    border: 0;
                    border-top: 3px solid var(--accent);
                    font-size: 0;
                    line-height: 0;
                    overflow: hidden;
                }

                .header-content {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                }

                .logo-section {
                    width: 14%;
                    min-width: 0;
                    vertical-align: top;
                    /* padding-right: 2mm; */
                }

                .header-info {
                    vertical-align: top;
                    padding-left: {{ $gapLogoInfoMm ?? 7 }}mm;
                    width: 55%;
                    min-width: 0;
                    overflow: hidden;
                    word-wrap: break-word;
                }

                .folio-section {
                    width: 31%;
                    min-width: 0;
                    vertical-align: top;
                    text-align: right;
                    padding-left: 2mm;
                    overflow: hidden;
                }

                .logo-img {
                    max-width: 100%;
                    /* max-height: 16mm; */
                    object-fit: contain;
                    border-radius: 2mm;
                }

                .logo-fallback {
                    width: 14mm;
                    height: 14mm;
                    background: var(--accent);
                    border-radius: 2mm;
                    color: #ffffff;
                    font-size: 11pt;
                    font-weight: bold;
                    text-align: center;
                    line-height: 14mm;
                }

                .company-header-name {
                    font-size: 9pt;
                    font-weight: 700;
                    color: var(--text-heading);
                    margin-bottom: 0.8mm;
                    line-height: 1.15;
                    letter-spacing: 0.02em;
                }

                .company-header-info {
                    font-size: 7.5pt;
                    color: #7f8c8d;
                    margin-bottom: 0.6mm;
                    line-height: 1.15;
                }

                .folio-label {
                    font-size: 6pt;
                    color: var(--accent);
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    font-weight: 600;
                    margin-bottom: 0.8mm;
                    line-height: 1.1;
                }

                .folio-number {
                    font-size: 9pt;
                    font-weight: 700;
                    color: var(--accent);
                    margin-bottom: 0.8mm;
                    letter-spacing: -0.5pt;
                    word-wrap: break-word;
                    line-height: 1.1;
                    overflow: hidden;
                }

                .folio-uuid {
                    font-size: 6pt;
                    color: #171a1d;
                    margin-bottom: 0.6mm;
                    line-height: 1.1;
                    word-break: break-all;
                }

                .folio-date {
                    font-size: 7pt;
                    color: #171a1d;
                    line-height: 1.15;
                }

                .header.header--compact {
                    margin-bottom: 0;
                    padding-bottom: 1mm;
                    border-bottom: none;
                }

                .header.header--compact .logo-section {
                    width: auto;
                    max-width: 28%;
                    vertical-align: middle;
                }

                .header.header--compact .logo-img {
                    max-width: 26mm;
                    max-height: 14mm;
                    width: auto;
                    height: auto;
                    object-fit: contain;
                    object-position: left center;
                    display: block;
                }

                .header.header--compact .logo-fallback {
                    width: 11mm;
                    height: 11mm;
                    font-size: 8pt;
                    line-height: 11mm;
                }

                .header.header--compact .header-info {
                    padding-left: 2mm;
                }

                .header.header--compact .company-header-name {
                    font-size: 7.2pt;
                    margin-bottom: 0.3mm;
                    line-height: 1.1;
                }

                .header.header--compact .company-header-info {
                    font-size: 6.5pt;
                    margin-bottom: 0.2mm;
                }

                .header.header--compact .folio-section {
                    padding-left: 1mm;
                }

                .header.header--compact .folio-label {
                    font-size: 5.5pt;
                    margin-bottom: 0.3mm;
                }

                .header.header--compact .folio-number {
                    font-size: 7.5pt;
                    margin-bottom: 0.3mm;
                }

                .header.header--compact .folio-date {
                    font-size: 6pt;
                }

                /* ========== 2) DATOS DEL RECEPTOR ========== */
                .receptor-section {
                    width: 100%;
                    margin-bottom: 4mm;
                    padding: 3mm 4mm;
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-left: 3px solid var(--accent);
                    page-break-inside: avoid;
                }

                .receptor-title {
                    font-size: 6.5pt;
                    color: var(--accent);
                    font-weight: 700;
                    margin-bottom: 1.5mm;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .receptor-name {
                    font-size: 9pt;
                    font-weight: 700;
                    color: #2c3e50;
                    margin-bottom: 1mm;
                    line-height: 1.15;
                }

                .receptor-info {
                    font-size: 7pt;
                    color: #5f6f89;
                    margin-bottom: 0.8mm;
                    line-height: 1.15;
                }

                /* ========== 3) DESCRIPCIÓN GENERAL ========== */
                .descripcion-section {
                    width: 100%;
                    margin-bottom: 4mm;
                    padding: 3mm 4mm;
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-left: 3px solid var(--accent);
                    page-break-inside: avoid;
                }

                .descripcion-title {
                    font-size: 6.5pt;
                    font-weight: 700;
                    color: var(--accent);
                    margin-bottom: 1.5mm;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .descripcion-text {
                    font-size: 7pt;
                    color: #34495e;
                    text-align: justify;
                    line-height: 1.25;
                }

                /* ========== 4) TÍTULO Y TABLA PRESUPUESTO ========== */
                .presupuesto-title {
                    font-size: 9pt;
                    font-weight: 700;
                    color: var(--text-heading);
                    margin-top: 8mm;
                    margin-bottom: 3mm;
                    line-height: 1.15;
                    text-align: center;
                    text-transform: uppercase;
                    letter-spacing: 0.12em;
                    padding-bottom: 2mm;
                    border-bottom: 1px solid var(--accent);
                    width: 100%;
                }

                .presupuesto-table {
                    width: 100%;
                    max-width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 10mm;
                    table-layout: fixed;
                    overflow: hidden;
                }

                /*
                .presupuesto-table tr {
                    page-break-inside: avoid;
                } */

                .presupuesto-table thead {
                    display: table-header-group;
                    background: var(--accent);
                    color: #ffffff;
                }

                .presupuesto-table thead th {
                    padding: 1.5mm 1mm;
                    font-size: 6pt;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    text-align: center;
                    border: 1px solid var(--accent-dark);
                    line-height: 1.1;
                }

                .presupuesto-table thead th:first-child {
                    width: 5%;
                }

                .presupuesto-table thead th:nth-child(2) {
                    width: 38%;
                    text-align: left;
                    padding-left: 1.5mm;
                }

                .presupuesto-table thead th:nth-child(3) {
                    width: 10%;
                }

                .presupuesto-table thead th:nth-child(4) {
                    width: 10%;
                }

                .presupuesto-table thead th:nth-child(5),
                .presupuesto-table thead th:nth-child(6) {
                    width: 18%;
                    text-align: right;
                    padding-right: 1mm;
                }

                .presupuesto-table tbody td {
                    padding: 1.2mm 1mm;
                    font-size: 6.5pt;
                    border: 1px solid #e9ecef;
                    vertical-align: top;
                    line-height: 1.15;
                    overflow: hidden;
                }

                .presupuesto-table tbody td:first-child {
                    text-align: center;
                    font-weight: 600;
                    color: #7f8c8d;
                }

                .presupuesto-table tbody td:nth-child(2) {
                    text-align: left;
                    color: #2c3e50;
                    padding-left: 1.5mm;
                    word-wrap: break-word;
                }

                .presupuesto-table tbody td:nth-child(3),
                .presupuesto-table tbody td:nth-child(4) {
                    text-align: center;
                    color: #5f6f89;
                    text-transform: uppercase;
                }

                .presupuesto-table tbody td:nth-child(5),
                .presupuesto-table tbody td:nth-child(6) {
                    text-align: right;
                    color: #2c3e50;
                    padding-right: 1mm;
                }

                .presupuesto-table tbody td:nth-child(6) {
                    font-weight: 600;
                }

                .presupuesto-table tbody tr:nth-child(even) {
                    background: #f8f9fa;
                }

                .no-conceptos {
                    padding: 6mm;
                    text-align: center;
                    color: #95a5a6;
                    font-style: italic;
                    font-size: 7pt;
                }

                .presupuesto-table tbody tr.linea-parrafo {
                    page-break-inside: avoid;
                    break-inside: avoid;
                }

                .presupuesto-table tbody tr.linea-parrafo td {
                    text-align: left;
                    font-weight: 400;
                    color: #334155;
                    padding: 2mm 2.5mm;
                    line-height: 1.45;
                    white-space: normal;
                    word-wrap: break-word;
                    background: #f8fafc;
                    box-sizing: border-box;
                    vertical-align: top;
                }

                .presupuesto-table tbody tr.linea-parrafo td:first-child {
                    text-align: center;
                    color: #6b7280;
                    font-weight: 600;
                }

                .presupuesto-table tbody tr.linea-con-imagen {
                    height: 18mm;
                }

                .concepto-imagen-wrap {
                    margin-top: 1mm;
                }

                .concepto-imagen {
                    width: 15mm;
                    height: 15mm;
                    object-fit: cover;
                    border: 1px solid #e9ecef;
                    border-radius: 1mm;
                }

                .concepto-proveedor-origen {
                    display: flex;
                    align-items: center;
                    gap: 1.5mm;
                    margin-top: 1mm;
                }

                .concepto-proveedor-logo {
                    width: 5mm;
                    height: 5mm;
                    object-fit: contain;
                    border-radius: 0.5mm;
                    background: #fff;
                }

                .concepto-proveedor-nombre {
                    font-size: 7pt;
                    font-weight: 600;
                    color: #475569;
                }

                /* ========== 5) TOTALES (alineado con tabla) ========== */
                .terms-block {
                    margin-bottom: 4mm;
                    page-break-inside: auto;
                    page-break-before: auto;
                }

                .totales-section {
                    page-break-inside: avoid;
                    break-inside: avoid;
                }

                .importe-con-letra {
                    margin-top: 2mm;
                    width: 100%;
                    border: 1px solid #e8eef4;
                    border-radius: 1mm;
                    background: #ffffff;
                    page-break-inside: avoid;
                    overflow: hidden;
                }

                .importe-con-letra-label {
                    background: #fafbfc;
                    text-align: center;
                    font-size: 6pt;
                    font-weight: 400;
                    letter-spacing: 0.02em;
                    text-transform: none;
                    color: #94a3b8;
                    padding: 1mm 2mm;
                    border-bottom: 1px solid #eef2f6;
                }

                .importe-con-letra-valor {
                    text-align: center;
                    font-size: 6.5pt;
                    font-weight: 400;
                    color: #64748b;
                    padding: 1.8mm 2.5mm;
                    line-height: 1.3;
                    background: #fcfdfe;
                    white-space: normal;
                    word-break: break-word;
                }

                .totales-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                }

                .totales-table td {
                    padding: 1mm 1mm 1.5mm 1mm;
                    font-size: 7pt;
                    vertical-align: middle;
                    white-space: nowrap;
                    overflow: hidden;
                }

                .totales-table td:first-child {
                    width: 58%;
                    text-align: right;
                    color: #5f6f89;
                    padding-right: 2mm;
                }

                .totales-table .totales-meta-value {
                    text-align: right;
                    color: #2c3e50;
                    font-weight: 600;
                    padding-right: 0;
                }

                .totales-table .totales-money-sign-col {
                    width: 12%;
                    text-align: right;
                    color: #64748b;
                    font-weight: 600;
                    padding-right: 1mm;
                }

                .totales-table .totales-money-amount-col {
                    width: 30%;
                    text-align: right;
                    color: #2c3e50;
                    font-weight: 600;
                    padding-right: 0;
                    font-variant-numeric: tabular-nums;
                }

                .totales-table .total-line-final td {
                    padding-top: 2mm;
                    border-top: 2px solid var(--accent);
                }

                .totales-table .total-line-final td:first-child {
                    font-size: 9pt;
                    font-weight: 700;
                    color: var(--text-heading);
                }

                .totales-table .total-line-final td:last-child {
                    font-size: 10pt;
                    font-weight: 700;
                    color: var(--accent);
                }

                .totales-table .total-line-final .totales-money-sign-col,
                .totales-table .total-line-final .totales-money-amount-col {
                    color: var(--accent);
                    font-weight: 700;
                }

                /* ========== 6) TÉRMINOS Y CONDICIONES (al final de la última página) ========== */
                .terminos-section {
                    margin-top: 5mm;
                    padding-top: 2mm;
                    border-top: 1px solid #d1d5db;
                    page-break-inside: auto;

                }

                .terminos-main-title,
                .terminos-title {
                    font-size: 7.5pt;
                    font-weight: 700;
                    color: var(--accent);
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                    margin-bottom: 1.5mm;
                    page-break-after: avoid;
                }

                .terminos-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                    counter-reset: item;
                }

                .terminos-list li {
                    font-size: 6.2pt;
                    color: #4b5563;
                    line-height: 1.08;
                    /* 🔥 más compacto */
                    margin-bottom: 0.5mm;
                    /* 🔥 menos espacio */
                    padding-left: 4.5mm;
                    position: relative;
                    text-align: justify;
                }

                .terminos-list li::before {
                    counter-increment: item;
                    content: counter(item) ".";
                    position: absolute;
                    left: 0;
                    top: 0;
                    font-weight: 600;
                    color: var(--accent);
                }

                /* ========== 7) OBSERVACIONES GENERALES ========== */
                .observaciones-section {
                    margin-bottom: 0;
                    margin-top: 3mm;
                    padding: 4mm 6mm;
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 2mm;
                }

                .observaciones-title {
                    font-family: 'DejaVu Sans', Arial, sans-serif;
                    font-size: 8pt;
                    font-weight: 700;
                    color: var(--accent);
                    margin-bottom: 2mm;
                    text-transform: uppercase;
                    letter-spacing: 0.5pt;
                    line-height: 1.2;
                }

                .observaciones-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .observaciones-list li {
                    font-size: 7pt;
                    color: #374151;
                    line-height: 1.5;
                    margin-bottom: 1.5mm;
                    padding-left: 4mm;
                    position: relative;
                }

                .observaciones-list li::before {
                    content: "•";
                    position: absolute;
                    left: 0;
                    color: var(--accent);
                    font-weight: bold;
                }

                /* ========== 8) PIE DE PÁGINA (ancho = página - márgenes, logos izq, centro, QR derecha) ========== */
                .footer {
                    position: fixed;
                    bottom: 6mm;
                    left: {{ $margenMm }}mm;
                    right: {{ $margenMm }}mm;
                    height: {{ $footerHeightMm - 2 }}mm;
                    min-height: {{ $footerHeightMm - 2 }}mm;
                    padding: 1mm 0 2mm;
                    font-size: 6.5pt;
                    color: #6b7280;
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
                    /* 🔥 clave */
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
                    width: 8mm;
                    height: 8mm;
                    object-fit: contain;
                    display: block;
                }

                .footer-logo-placeholder {
                    width: 8mm;
                    height: 8mm;
                    min-width: 8mm;
                    background: #e5e7eb;
                    border-radius: 1.5mm;
                    font-size: 5pt;
                    font-weight: 700;
                    color: #6b7280;
                    text-align: center;
                    line-height: 8mm;
                }

                .footer-center-content {
                    text-align: center;
                    width: 100%;
                }

                .footer-pages {
                    font-weight: 600;
                    color: #374151;
                    font-size: 7pt;
                    margin-bottom: 0.6mm;
                    min-height: 3mm;
                }

                .footer-slogan {
                    font-style: italic;
                    color: #6b7280;
                    font-size: 6pt;
                    /* margin-bottom: 0.4mm; */
                }

                .footer-webs {
                    font-size: 6pt;
                }

                .footer-webs-link {
                    color: var(--accent);
                    text-decoration: none;
                }

                .footer-webs-sep {
                    color: #9ca3af;
                    margin: 0 1mm;
                }

                .footer-qr {
                    display: inline-block;
                    width: 12mm;
                    height: 12mm;
                    vertical-align: middle;
                }

                .footer-qr img {
                    width: 100%;
                    height: 100%;
                    object-fit: contain;
                }

                .page-break-avoid {
                    page-break-inside: avoid;
                }

                .after-table-space {
                    height: {{ $footerHeightMm + 5 }}mm;
                }

                .after-table-space--compact {
                    height: 3mm;
                }

                .pdf-pagina-con-subencabezado {
                    padding-top: 2mm;
                    box-sizing: border-box;
                }

                .page-break {
                    page-break-before: always;
                }

                .anexos-page {
                    width: 100%;
                }

                .anexos-preview-header {
                    margin-bottom: 2.5mm;
                    padding-bottom: 1.5mm;
                    border-bottom: 1px solid #d1d5db;
                }

                .anexos-preview-title {
                    font-size: 11pt;
                    font-weight: 700;
                    color: var(--text-heading);
                    line-height: 1.15;
                    margin: 0;
                }

                .anexo-simple {
                    width: 100%;
                    padding: 2.8mm 0;
                    border-bottom: 1px solid #e5e7eb;
                    page-break-inside: avoid;
                }

                .anexo-simple:last-child {
                    border-bottom: none;
                }

                .anexo-simple-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                }

                .anexo-simple-media,
                .anexo-simple-text {
                    vertical-align: top;
                }

                .anexo-simple-media {
                    width: 52mm;
                    padding-right: 3.5mm;
                }

                .anexo-simple-image-wrap {
                    height: 29mm;
                    overflow: hidden;
                    background: #f8fafc;
                    text-align: center;
                }

                .anexo-simple-image {
                    display: block;
                    width: auto;
                    height: auto;
                    max-width: 100%;
                    max-height: 100%;
                    margin: 0 auto;
                }

                .anexo-simple-heading {
                    font-size: 8.4pt;
                    font-weight: 700;
                    color: var(--text-heading);
                    line-height: 1.2;
                    margin-bottom: 1.1mm;
                }

                .anexo-simple-desc {
                    font-size: 7.1pt;
                    color: #475569;
                    line-height: 1.3;
                    white-space: pre-wrap;
                    word-break: break-word;
                    margin-bottom: 1.1mm;
                }

                .anexo-simple-price {
                    font-size: 7.8pt;
                    font-weight: 700;
                    color: var(--accent);
                }

                @include('presupuestos.partials.presupuesto-pdf-debug-bordes-css')
            </style>
        </head>

        <body>
            {{-- Pie de página: logos izq, centro: Página N de N + slogan + urls, QR derecha --}}
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
                                        <span
                                            class="footer-logo-placeholder">{{ strtoupper(substr($key, 0, 1)) }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="footer-center">
                        <div class="footer-center-content">
                            <div class="footer-slogan">"Creador de presupuestos"</div>
                            <div class="footer-webs">
                                <a href="https://heventec.com" class="footer-webs-link">heventec.com</a><span
                                    class="footer-webs-sep">|</span><a href="https://gestion.heventec.com/"
                                    class="footer-webs-link">gestion.heventec.com</a>
                                <div class="footer-pages">&nbsp;</div>
                            </div>
                        </div>
                    </div>
                    <div class="footer-right">
                        @if (isset($presupuesto['qr_code']) && $presupuesto['qr_code'])
                            <div class="footer-qr">
                                <img src="{{ $presupuesto['qr_code'] }}" alt="Ver versión web"
                                    title="Ver versión web" />
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="margin-sides">
                <div class="pdf-seccion pdf-seccion--presupuesto">
                <div class="document-container">
                    <div class="document-main">
                        <!-- 1) ENCABEZADO -->
                        @include('presupuestos.partials.presupuesto-pdf-header-default')

                        <!-- 2) DATOS DEL RECEPTOR -->
                        <div class="receptor-section">
                            <div class="receptor-title">Dirigido a:</div>

                            @foreach ($presupuesto['receptor_lineas'] ?? [] as $idx => $linea)
                                <div class="{{ $idx === 0 ? 'receptor-name' : 'receptor-info' }}">{{ $linea }}</div>
                            @endforeach
                        </div>

                        <!-- 3) NOMBRE / DESCRIPCIÓN GENERAL -->
                        @if (($presupuesto['nombre_presupuesto'] ?? null) || ($presupuesto['concepto_general'] ?? null))
                            <div class="descripcion-section">
                                @if ($presupuesto['nombre_presupuesto'] ?? null)
                                    <div class="descripcion-title">{{ $presupuesto['nombre_presupuesto'] }}</div>
                                @else
                                    <div class="descripcion-title">Descripción general</div>
                                @endif
                                @if ($presupuesto['concepto_general'] ?? null)
                                    <div class="descripcion-text">{{ $presupuesto['concepto_general'] }}</div>
                                @endif
                            </div>
                        @endif

                        <!-- 4) TÍTULO Y TABLA PRESUPUESTO -->
                        <div class="presupuesto-title">Presupuesto</div>
                        <table class="presupuesto-table">
                            <thead>
                                @include('presupuestos.partials.presupuesto-pdf-tabla-conceptos-thead', [
                                    'variant' => 'default',
                                ])
                            </thead>
                            <tbody>
                                @php
                                    $conceptos = $conceptosListaPdf;
                                    $subtotal = 0;
                                    foreach ($conceptosListaPdf as $conceptoSubtotal) {
                                        if (! is_array($conceptoSubtotal)) {
                                            continue;
                                        }
                                        if (! \App\Support\PresupuestoParrafoPdf::esLineaParrafo($conceptoSubtotal)) {
                                            $cant = $conceptoSubtotal['cantidad'] ?? 1;
                                            $precio = $conceptoSubtotal['precio_unitario'] ?? 0;
                                            $subtotal += $cant * $precio;
                                        }
                                    }
                                @endphp
                                @if (count($conceptos) > 0)
                                    @foreach ($conceptos as $index => $concepto)
                                        @include('presupuestos.partials.presupuesto-pdf-fila-concepto', [
                                            'concepto' => $concepto,
                                            'numeroFila' => $index + 1,
                                            'variant' => 'default',
                                        ])
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="6" class="no-conceptos">No hay conceptos registrados
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>

                        <!-- 5) TOTALES -->
                        @if ($presupuesto['config_mostrar_totales'] ?? true)
                        <div class="totales-section">
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
                            <table class="totales-table">
                                <tr>
                                    <td>Subtotal:</td>
                                    <td class="totales-money-sign-col">{{ $monedaPrefijo }}</td>
                                    <td class="totales-money-amount-col">{{ number_format($totalesDoc['subtotal'], 2, '.', ',') }}</td>
                                </tr>
                                @if ($totalesDoc['mostrar_descuento'])
                                    <tr>
                                        <td>Descuento ({{ $totalesDoc['porcentaje_descuento'] }}%):</td>
                                        <td class="totales-money-sign-col">- {{ $monedaPrefijo }}</td>
                                        <td class="totales-money-amount-col">{{ number_format($totalesDoc['monto_descuento'], 2, '.', ',') }}</td>
                                    </tr>
                                @endif
                                @if ($conIva)
                                    <tr>
                                        <td>IVA ({{ number_format($ivaPorcentaje, 0) }}%):</td>
                                        <td class="totales-money-sign-col">{{ $monedaPrefijo }}</td>
                                        <td class="totales-money-amount-col">{{ number_format($ivaTotal, 2, '.', ',') }}</td>
                                    </tr>
                                @endif
                                <tr class="total-line-final">
                                    <td>TOTAL:</td>
                                    <td class="totales-money-sign-col">{{ $monedaPrefijo }}</td>
                                    <td class="totales-money-amount-col">{{ number_format($total, 2, '.', ',') }}</td>
                                </tr>
                            </table>
                            <div class="importe-con-letra">
                                <div class="importe-con-letra-label">Importe con letra:</div>
                                <div class="importe-con-letra-valor">
                                    {{ \App\Support\PresupuestoPdf::formatMontoLegal($total, $monedaCodigo) }}
                                </div>
                            </div>
                            <div class="after-table-space after-table-space--compact"></div>
                        </div>
                        @endif

                    @if ($tieneBloqueTerminos || $mostrarAtentamente)
                        <div class="presupuesto-cierre-terminos-atentamente">
                    @if ($tieneBloqueTerminos)
                        <div class="terms-block terms-block--after-presupuesto">
                            @include('presupuestos.partials.presupuesto-pdf-terminos', [
                                'variant' => 'default',
                                'terminosLista' => $terminosLista,
                                'validacionesLista' => $validacionesLista,
                                'observacionesLista' => $observacionesLista,
                            ])
                        </div>
                    @endif

                    @if ($mostrarAtentamente && ($cierreAtentamente['salto_pagina_antes'] ?? false))
                        <div class="page-break"></div>
                    @endif
                    @if ($mostrarAtentamente && (float) ($cierreAtentamente['reserva_pie_html_mm'] ?? 0) > 0)
                        <div
                            class="presupuesto-reserva-atentamente-pie"
                            style="height: {{ number_format((float) $cierreAtentamente['reserva_pie_html_mm'], 2, '.', '') }}mm;"
                            aria-hidden="true"></div>
                    @endif
                        </div>
                    @endif
                    </div>
                </div>
                </div>

                @include('presupuestos.partials.presupuesto-pdf-seccion-anexos', [
                    'anexosLista' => $anexosLista,
                    'tituloAnexos' => $tituloAnexos ?? 'Anexos',
                    'variant' => 'default',
                ])

                @include('presupuestos.partials.presupuesto-pdf-seccion-documentacion', [
                    'documentacionLista' => $documentacionLista,
                    'variant' => 'default',
                ])
            </div>
            @include('presupuestos.partials.presupuesto-pdf-page-scripts', [
                'pdf' => $pdf,
                'presupuesto' => $presupuesto,
                'paginaAtentamente' => (int) ($cierreAtentamente['pagina_atentamente'] ?? 0),
                'paginasTrasSeccionPresupuesto' => $paginasTrasSeccionPresupuesto,
            ])
        </body>

        </html>
