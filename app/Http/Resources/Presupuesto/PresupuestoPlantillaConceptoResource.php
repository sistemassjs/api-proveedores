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
            'descripcion' => $this->descripcion,
            'cantidad' => (float) $this->cantidad,
            'unidad' => $this->unidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'imagen_path' => PresupuestoAnexoArchivoResponse::archivoPathPublico($this->imagen_path),
            'imagen_url' => PresupuestoAnexoArchivoResponse::archivoUrl($this->imagen_path),
        ];
    }
}
