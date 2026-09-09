<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plantilla Blade para PDF de presupuesto
    |--------------------------------------------------------------------------
    |
    | classic  — plantilla original (presupuestos.pdf)
    | tailwind — plantilla alternativa con estilo tipo Tailwind (slate/indigo)
    |
    */
    'pdf_template' => env('PRESUPUESTO_PDF_TEMPLATE', 'tailwind'),

    /*
    | Imágenes de anexos: calidad al guardar y al embebir en PDF (DomPDF).
    */
    'anexo_imagen' => [
        'almacenamiento_max_lado_px' => (int) env('PRESUPUESTO_ANEXO_MAX_LADO', 1280),
        'almacenamiento_jpeg_calidad' => (int) env('PRESUPUESTO_ANEXO_JPEG_CALIDAD', 78),
        'pdf_max_lado_px' => (int) env('PRESUPUESTO_ANEXO_PDF_MAX_LADO', 900),
        'pdf_jpeg_calidad' => (int) env('PRESUPUESTO_ANEXO_PDF_JPEG_CALIDAD', 72),
    ],

    /*
    | Anexos PDF (merge al final del documento). Sin límite de páginas; solo tamaño en bytes.
    */
    'anexo_pdf' => [
        'max_bytes' => (int) env('PRESUPUESTO_ANEXO_PDF_MAX_BYTES', 50 * 1024 * 1024),
        // Ruta a gswin64c.exe / gs (opcional): reescribe PDFs a 1.4 para FPDI en merge.
        'ghostscript_bin' => env('PRESUPUESTO_ANEXO_PDF_GHOSTSCRIPT_BIN'),
    ],

];
