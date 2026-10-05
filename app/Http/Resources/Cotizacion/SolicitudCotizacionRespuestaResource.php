<?php

namespace App\Http\Resources\Cotizacion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudCotizacionRespuestaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'canales' => $this->canales,
            'mensaje' => $this->mensaje,
            'pdf_path' => $this->pdf_path,
            'email_estado' => $this->email_estado,
            'email_error' => $this->email_error,
            'whatsapp_modo' => $this->whatsapp_modo,
            'whatsapp_estado' => $this->whatsapp_estado,
            'whatsapp_link' => $this->whatsapp_link,
            'whatsapp_error' => $this->whatsapp_error,
            'payload_resumen' => $this->payload_resumen,
            'enviado_por' => $this->whenLoaded('enviadoPor', function () {
                return $this->enviadoPor ? [
                    'id' => $this->enviadoPor->id,
                    'name' => $this->enviadoPor->name,
                    'email' => $this->enviadoPor->email,
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
