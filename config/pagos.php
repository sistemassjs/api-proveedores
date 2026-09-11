<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Complemento de pago faltante si factura es PPD
    |--------------------------------------------------------------------------
    |
    | Si true, el reporte / documentos_faltantes marca complemento cuando
    | metodo_pago de la factura es PPD y aún no hay complemento cargado.
    | Se puede desactivar sin cambiar código de negocio.
    |
    */
    'marcar_complemento_faltante_si_ppd' => env('PAGOS_MARCAR_COMPLEMENTO_FALTANTE_SI_PPD', true),

    /*
    |--------------------------------------------------------------------------
    | Serie de folio para pagos directos
    |--------------------------------------------------------------------------
    |
    | true  = misma serie consecutiva de pagos SPP (folio_pago_spp_consecutivo)
    | false = reservado para serie aparte (no implementado aún)
    |
    */
    'pagos_directos_usan_misma_serie_folio' => env('PAGOS_DIRECTOS_MISMA_SERIE_FOLIO', true),

];
