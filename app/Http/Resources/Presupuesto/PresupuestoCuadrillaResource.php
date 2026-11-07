<?php

namespace App\Http\Resources\Presupuesto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresupuestoCuadrillaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'presupuesto_id' => (int) $this->presupuesto_id,
            'orden' => (int) $this->orden,
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'integrantes' => $this->integrantes,
            'unidad' => $this->unidad,
            'costo_unitario' => (float) $this->costo_unitario,
            'descripcion_matriz' => $this->descripcionParaMatriz(),
        ];
    }
}
