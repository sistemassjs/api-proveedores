<?php

namespace App\Http\Resources\Cotizacion;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudCotizacionDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'unidad' => $this->unidad,
            'codigo' => $this->codigo,
            'imagen' => PublicStorageUrl::make($this->imagen),
            'precio_referencia' => $this->precio_referencia !== null ? (float) $this->precio_referencia : null,
            'precio_unitario' => (float) $this->precio_unitario,
            'cantidad' => (float) $this->cantidad,
            'subtotal' => (float) $this->subtotal,
            'es_sugerencia_empresa' => (bool) $this->es_sugerencia_empresa,
            'motivo_sugerencia' => $this->motivo_sugerencia,
            'observaciones_linea' => $this->observaciones_linea,
            'orden' => (int) $this->orden,
        ];
    }
}
