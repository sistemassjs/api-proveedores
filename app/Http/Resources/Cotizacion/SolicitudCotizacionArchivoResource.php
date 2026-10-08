<?php

namespace App\Http\Resources\Cotizacion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudCotizacionArchivoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $solicitud = $this->whenLoaded('solicitud') ? $this->solicitud : null;

        return [
            'id' => $this->id,
            'proveedor_id' => $this->proveedor_id,
            'solicitud_cotizacion_id' => $this->solicitud_cotizacion_id,
            'respuesta_id' => $this->respuesta_id,
            'tipo' => $this->tipo,
            'nombre' => $this->nombre,
            'pdf_path' => $this->pdf_path,
            'folio' => $solicitud?->folio,
            'cliente_nombre' => $solicitud?->cliente_nombre,
            'creado_por' => $this->whenLoaded('creadoPor', function () {
                return $this->creadoPor ? [
                    'id' => $this->creadoPor->id,
                    'name' => $this->creadoPor->name,
                    'email' => $this->creadoPor->email,
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
