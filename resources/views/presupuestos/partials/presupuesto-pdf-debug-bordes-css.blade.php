{{--
  Depuración visual DomPDF: contornos 1px sin ensanchar cajas (outline).
  Leyenda:
  body #111 | margin-sides #444 | pdf-seccion presupuesto #e11 | salto-siguiente #e11 dashed |
  document-container #06c | document-main #080 | header #f00 | receptor #00f | descripcion #088 |
  tabla presupuesto #000 | totales #060 | importe-letra #606 | after-table-space #c0c |
  terms-block #90c | terminos/obs/tw-terms #c0f | atentamente bloques #f80 | atentamente-spacer #f00 |
  anexos/doc #a0a | footer dashed #999 | page-break #333
--}}
@if (!empty($pdfDebugBordesContenedores))
                body {
                    outline: 1px solid #111111;
                }

                .margin-sides {
                    outline: 1px solid #444444;
                }

                .pdf-seccion {
                    outline: 1px solid #888888;
                }

                .pdf-seccion--presupuesto {
                    outline: 1px solid #ee1111;
                }

                .pdf-seccion--presupuesto.pdf-seccion--salto-siguiente {
                    outline: 2px dashed #ee1111;
                }

                .document-container {
                    outline: 1px solid #0066cc;
                }

                .document-main {
                    outline: 1px solid #008800;
                }

                .header,
                .tw-header {
                    outline: 1px solid #ff0000;
                }

                .receptor-section,
                .tw-card {
                    outline: 1px solid #0000ff;
                }

                .descripcion-section,
                .tw-desc-box {
                    outline: 1px solid #008888;
                }

                .presupuesto-title,
                .tw-section-title {
                    outline: 1px solid #8b4513;
                }

                .presupuesto-table,
                .tw-table {
                    outline: 1px solid #000000;
                }

                .totales-section,
                .tw-totals-wrap {
                    outline: 1px solid #006600;
                }

                .tw-totals-inner {
                    outline: 1px solid #339933;
                }

                .importe-con-letra {
                    outline: 1px solid #666600;
                }

                .after-table-space {
                    outline: 1px solid #cc00cc;
                    min-height: 0;
                }

                .terms-block {
                    outline: 1px solid #9900cc;
                }

                .terms-block--after-presupuesto {
                    outline: 1px solid #660099;
                }

                .terms-block--pagina-siguiente,
                .presupuesto-cierre-terminos-atentamente {
                    outline: 1px solid #6633cc;
                }

                .terminos-section,
                .tw-terms {
                    outline: 1px solid #cc00ff;
                }

                .observaciones-section {
                    outline: 1px solid #00cccc;
                }

                .pdf-seccion-presupuesto__atentamente,
                .document-closing-atentamente {
                    outline: 1px solid #ff8800;
                }

                .atentamente-plain {
                    outline: 1px solid #ff6600;
                }

                .atentamente-plain .atentamente-spacer,
                .atentamente-spacer {
                    outline: 1px solid #ff0000;
                }

                .document-main-spacer,
                .document-main-spacer--atentamente {
                    outline: 1px solid #ff00ff;
                }

                .pdf-seccion--anexos,
                .pdf-seccion--documentacion {
                    outline: 1px solid #aa00aa;
                }

                .pdf-seccion-documentacion__pagina,
                .anexos-page,
                .tw-anexos-page {
                    outline: 1px solid #993399;
                }

                .page-break,
                .tw-page-break {
                    outline: 1px solid #333333;
                    background: rgba(255, 255, 0, 0.15);
                }

                .footer {
                    outline: 1px dashed #999999;
                }

                .footer-left,
                .footer-center,
                .footer-right {
                    outline: 1px solid #aaaaaa;
                }
@endif
