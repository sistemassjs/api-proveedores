<?php

namespace App\Http\Resources\Presupuesto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PresupuestoPlantillaConceptoComponente
 */
class PresupuestoPlantillaConceptoComponenteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $anidados = is_array($this->componentes_anidados) ? $this->componentes_anidados : [];

        return [
            'id' => $this->id,
            'orden' => (int) $this->orden,
            'categoria' => $this->categoria,
            'catalogo_concepto_id' => $this->catalogo_concepto_id,
            'clave_snapshot' => $this->clave_snapshot,
            'descripcion' => $this->descripcion,
            'unidad' => $this->unidad,
            'cantidad' => (float) $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'importe' => (float) $this->importe,
            'es_compuesto_ref' => (bool) ($this->es_compuesto_ref ?? false) || count($anidados) > 0,
            'componentes_anidados' => $anidados,
        ];
    }
}
