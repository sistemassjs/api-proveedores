<?php

namespace App\Http\Resources\Presupuesto;

use App\Models\PresupuestoConcepto;
use App\Support\PresupuestoAnexoArchivoResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de un concepto individual del presupuesto.
 */
class PresupuestoConceptoResource extends JsonResource
{
    /**
     * Transforma el recurso en arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => (int) $this->numero,
            'tipo' => $this->tipo ?? PresupuestoConcepto::TIPO_CONCEPTO,
            'descripcion' => $this->descripcion,
            'cantidad' => (float) $this->cantidad,
            'unidad' => $this->unidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'precio_total' => (float) $this->precio_total,
            'tiene_matriz' => (bool) ($this->tiene_matriz ?? false),
            'componentes' => PresupuestoConceptoComponenteResource::collection(
                $this->whenLoaded('componentes')
            ),
            'imagen_path' => PresupuestoAnexoArchivoResponse::archivoPathPublico($this->imagen_path),
            'imagen_url' => PresupuestoAnexoArchivoResponse::archivoUrl($this->imagen_path),
            'imagen_base64' => PresupuestoAnexoArchivoResponse::solicitaArchivoBase64($request)
                ? PresupuestoAnexoArchivoResponse::archivoBase64($this->imagen_path)
                : null,
            'proveedor_nombre' => $this->proveedor_nombre,
            'proveedor_logo_url' => $this->proveedor_logo_url,
        ];
    }
}

