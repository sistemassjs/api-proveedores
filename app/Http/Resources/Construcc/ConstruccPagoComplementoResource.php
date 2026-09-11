<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccPagoComplementoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pago_factura_id' => $this->pago_factura_id,
            'pago_spp_id' => $this->pago_spp_id,
            'folio_complemento' => $this->folio_complemento,
            'url_pdf' => $this->ruta_archivo_pdf
                ? route('construcc.pagos-spp.complementos.descargar-pdf', ['complemento' => $this->id])
                : null,
            'url_xml' => $this->ruta_archivo_xml
                ? route('construcc.pagos-spp.complementos.descargar-xml', ['complemento' => $this->id])
                : null,
            'tiene_pdf' => ! empty($this->ruta_archivo_pdf),
            'tiene_xml' => ! empty($this->ruta_archivo_xml),
            'created_at' => optional($this->created_at)?->toDateTimeString(),
        ];
    }
}
