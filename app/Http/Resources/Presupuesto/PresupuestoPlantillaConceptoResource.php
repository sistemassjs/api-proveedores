<?php

namespace App\Http\Resources\Presupuesto;

use App\Support\PresupuestoAnexoArchivoResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PresupuestoPlantillaConcepto
 */
class PresupuestoPlantillaConceptoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => (int) $this->numero,
            'tipo' => $this->tipo ?? 'concepto',
            'tiene_matriz' => (bool) ($this->tiene_matriz ?? false),
            'indirectos_modo' => ($this->indirectos_modo ?? 'general') === 'propio' ? 'propio' : 'general',
            'indirecto_oficina_porcentaje' => $this->indirecto_oficina_porcentaje !== null ? (float) $this->indirecto_oficina_porcentaje : null,
            'indirecto_campo_porcentaje' => $this->indirecto_campo_porcentaje !== null ? (float) $this->indirecto_campo_porcentaje : null,
            'indirecto_utilidad_porcentaje' => $this->indirecto_utilidad_porcentaje !== null ? (float) $this->indirecto_utilidad_porcentaje : null,
            'indirecto_cargos_porcentaje' => $this->indirecto_cargos_porcentaje !== null ? (float) $this->indirecto_cargos_porcentaje : null,
            'descripcion' => $this->descripcion,
            'cantidad' => (float) $this->cantidad,
            'unidad' => $this->unidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'imagen_path' => PresupuestoAnexoArchivoResponse::archivoPathPublico($this->imagen_path),
            'imagen_url' => PresupuestoAnexoArchivoResponse::archivoUrl($this->imagen_path),
            'componentes' => PresupuestoPlantillaConceptoComponenteResource::collection(
                $this->whenLoaded('componentes')
            ),
        ];
    }
}
