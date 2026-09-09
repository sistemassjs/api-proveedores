<?php

namespace App\Http\Resources\Presupuesto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresupuestoEstadoLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'presupuesto_id' => (int) $this->presupuesto_id,
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                ];
            }),
            'fecha' => $this->fecha?->format('Y-m-d H:i:s'),
            'estado_anterior' => $this->estado_anterior,
            'estado' => $this->estado,
            'nota' => $this->nota,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
