<?php

namespace App\Http\Resources\Presupuesto;

use App\Support\PresupuestoAnexoPdfArchivoResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresupuestoAnexoPdfResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'presupuesto_id' => (int) $this->presupuesto_id,
            'titulo' => $this->titulo,
            'orden' => (int) $this->orden,
            'paginas' => (int) $this->paginas,
            'mostrar_estampado' => (bool) ($this->mostrar_estampado ?? true),
            'mostrar_numero_pagina' => (bool) ($this->mostrar_numero_pagina ?? true),
            'mostrar_datos_presupuesto' => (bool) ($this->mostrar_datos_presupuesto ?? true),
            'archivo_url' => PresupuestoAnexoPdfArchivoResponse::archivoUrl($this->archivo_path),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
