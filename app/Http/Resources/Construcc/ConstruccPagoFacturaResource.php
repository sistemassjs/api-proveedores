<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccPagoFacturaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pago_spp_id' => $this->pago_spp_id,
            'folio_factura' => $this->folio_factura,
            'metodo_pago' => $this->metodo_pago,
            'monto' => $this->monto !== null ? (float) $this->monto : null,
            'url_factura_pdf' => $this->ruta_archivo_factura_pdf
                ? route('construcc.pagos-spp.facturas.descargar-pdf', ['factura' => $this->id])
                : null,
            'url_factura_xml' => $this->ruta_archivo_factura_xml
                ? route('construcc.pagos-spp.facturas.descargar-xml', ['factura' => $this->id])
                : null,
            'tiene_pdf' => ! empty($this->ruta_archivo_factura_pdf),
            'tiene_xml' => ! empty($this->ruta_archivo_factura_xml),
            'documentos_faltantes' => $this->documentosFaltantes(),
            'complementos' => ConstruccPagoComplementoResource::collection(
                $this->whenLoaded('complementos')
            ),
            'created_at' => optional($this->created_at)?->toDateTimeString(),
        ];
    }
}
