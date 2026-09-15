<?php

namespace App\Http\Resources\Presupuesto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresupuestoConceptoComponenteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orden' => (int) $this->orden,
            'categoria' => $this->categoria,
            'catalogo_concepto_id' => $this->catalogo_concepto_id !== null
                ? (int) $this->catalogo_concepto_id
                : null,
            'clave_snapshot' => $this->clave_snapshot,
            'descripcion' => $this->descripcion,
            'unidad' => $this->unidad,
            'cantidad' => (float) $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'importe' => (float) $this->importe,
        ];
    }
}
