<?php

namespace App\Http\Resources\Construcc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstruccOrdenCompraResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_orden' => $this->numero_orden,
            'fecha_orden' => $this->fecha_orden?->format('Y-m-d'),
            'fecha_aprobacion' => $this->fecha_aprobacion?->format('Y-m-d H:i:s'),
            'importe_total' => (float) $this->importe_total,
            'estado' => [
                'codigo' => $this->estado->value,
                'label' => $this->estado->label(),
                'color' => $this->estado->color(),
            ],
            'observaciones' => $this->observaciones,
            'metadata_json' => $this->metadata_json,

            // Campos calculados
            'monto_sp_asociado' => (float) $this->monto_sp_asociado,
            'sp_count' => (int) $this->sp_count,
            'monto_disponible' => (float) $this->getMontoDisponible(),
            'puede_generar_sp' => $this->puedeGenerarSolicitudPago(),

            // Información de alertas
            'dias_sin_sp' => $this->dias_sin_sp ?? $this->getDiasSinSolicitudPago(),
            'nivel_alerta' => $this->nivel_alerta ?? $this->getNivelAlerta(),
            'mensaje_alerta' => $this->mensaje_alerta ?? null,

            // Relaciones
            'proveedor' => $this->whenLoaded('proveedor', function () {
                return [
                    'id' => $this->proveedor->id,
                    'nombre_comercial' => $this->proveedor->nombre_comercial,
                    'rfc' => $this->proveedor->rfc,
                ];
            }),

            'empresa_construcc' => $this->whenLoaded('empresaConstrucc', function () {
                return [
                    'id' => $this->empresaConstrucc->id,
                    'nombre' => $this->empresaConstrucc->nombre,
                    'rfc' => $this->empresaConstrucc->rfc,
                ];
            }),

            'detalles' => ConstruccOrdenCompraDetalleResource::collection($this->whenLoaded('detalles')),

            'solicitudes_pago' => $this->whenLoaded('solicitudesPago', function () {
                return $this->solicitudesPago->map(function ($sp) {
                    return [
                        'id' => $sp->id,
                        'numero_folio_solicitud' => $sp->numero_folio_solicitud,
                        'monto_total' => (float) $sp->monto_total,
                        'estado_solicitud' => $sp->estado_solicitud,
                        'fecha_creacion' => $sp->created_at?->format('Y-m-d H:i:s'),
                        'monto_asociado' => (float) $sp->pivot->monto_asociado,
                        'fecha_vinculacion' => $sp->pivot->fecha_vinculacion?->format('Y-m-d H:i:s'),
                        'notas' => $sp->pivot->notas,
                    ];
                });
            }),

            // Estadísticas
            'porcentaje_convertido' => $this->importe_total > 0 ?
                round(($this->monto_sp_asociado / $this->importe_total) * 100, 2) : 0,

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'estados_disponibles' => collect(config('ordenes-compra.estados'))->map(function ($config, $estado) {
                    return [
                        'codigo' => $estado,
                        'label' => $config['label'],
                        'color' => $config['color'],
                        'puede_generar_sp' => $config['puede_generar_sp'],
                    ];
                }),
                'thresholds_alertas' => config('ordenes-compra.alertas.thresholds'),
            ],
        ];
    }
}
