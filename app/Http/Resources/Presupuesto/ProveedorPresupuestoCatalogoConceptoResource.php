<?php

namespace App\Http\Resources\Presupuesto;

use App\Support\PresupuestoAnexoArchivoResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProveedorPresupuestoCatalogoConceptoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proveedor_id' => $this->proveedor_id,
            'descripcion' => $this->descripcion,
            'categoria' => $this->categoria,
            'unidad' => $this->unidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'imagen_path' => PresupuestoAnexoArchivoResponse::archivoPathPublico($this->imagen_path),
            'imagen_url' => PresupuestoAnexoArchivoResponse::archivoUrl($this->imagen_path),
            'imagen_base64' => PresupuestoAnexoArchivoResponse::solicitaArchivoBase64($request)
                ? PresupuestoAnexoArchivoResponse::archivoBase64($this->imagen_path)
                : null,
            'activo' => (bool) ($this->activo ?? true),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
