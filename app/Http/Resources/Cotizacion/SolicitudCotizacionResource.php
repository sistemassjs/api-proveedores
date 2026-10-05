<?php

namespace App\Http\Resources\Cotizacion;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudCotizacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $proveedor = $this->whenLoaded('proveedor') ? $this->proveedor : null;

        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'origen' => $this->origen,
            'estatus' => $this->estatus,
            'proveedor_id' => $this->proveedor_id,
            'proveedor' => $proveedor ? [
                'id' => $proveedor->id,
                'nombre_comercial' => $proveedor->nombre_comercial,
                'razon_social' => $proveedor->razon_social,
                'logo' => PublicStorageUrl::make($proveedor->logo),
            ] : null,
            'cliente_nombre' => $this->cliente_nombre,
            'cliente_email' => $this->cliente_email,
            'cliente_telefono' => $this->cliente_telefono,
            'cliente_whatsapp' => $this->cliente_whatsapp,
            'cliente_notas' => $this->cliente_notas,
            'observaciones_internas' => $this->observaciones_internas,
            'total' => (float) $this->total,
            'respondida_at' => $this->respondida_at?->toISOString(),
            'cerrada_at' => $this->cerrada_at?->toISOString(),
            'detalles' => SolicitudCotizacionDetalleResource::collection($this->whenLoaded('detalles')),
            'respuestas' => SolicitudCotizacionRespuestaResource::collection($this->whenLoaded('respuestas')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
