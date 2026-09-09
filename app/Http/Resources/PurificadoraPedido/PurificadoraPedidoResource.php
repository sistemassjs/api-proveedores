<?php

namespace App\Http\Resources\PurificadoraPedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurificadoraPedidoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'calle' => $this->calle,
            'numero' => $this->numero,
            'colonia' => $this->colonia,
            'codigoPostal' => $this->codigo_postal,
            'municipio' => $this->municipio,
            'cantidadGarrafones' => (int) $this->cantidad_garrafones,
            'precioUnitario' => (float) $this->precio_unitario,
            'total' => (float) $this->total,
            'notas' => $this->notas,
            'estado' => (int) $this->estado,
            'pendienteFecha' => $this->pendiente_fecha,
            'enProcesoFecha' => $this->en_proceso_fecha,
            'completadoFecha' => $this->completado_fecha,
            'canceladoFecha' => $this->cancelado_fecha,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
