<?php

namespace App\Http\Resources\Import;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportHistoryResource extends JsonResource
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
            'job_id' => $this->job_id,
            'proveedor_id' => $this->proveedor_id,
            'tipo' => $this->tipo,
            'tipo_label' => $this->getTipoLabel(),
            'archivo' => $this->archivo,
            'archivo_url' => PublicStorageUrl::make($this->archivo),
            'formato' => $this->formato,
            'estado' => $this->estado,
            'estado_label' => $this->getEstadoLabel(),
            'estado_color' => $this->getEstadoColor(),
            'fase' => $this->fase,
            'fase_label' => $this->getFaseLabel(),
            'logs' => $this->logs,
            'eta_seconds' => $this->eta_seconds,
            'eta_formatted' => $this->eta_seconds ? gmdate('H:i:s', $this->eta_seconds) : null,
            'mem_peak_mb' => $this->mem_peak_mb,
            'mem_peak_formatted' => $this->mem_peak_mb ? number_format($this->mem_peak_mb, 2).' MB' : null,
            'total_registros' => $this->total_registros,
            'nuevos' => $this->nuevos,
            'actualizados' => $this->actualizados,
            'eliminados' => $this->eliminados,
            'errores' => $this->errores,
            'procesados' => ($this->nuevos ?? 0) + ($this->actualizados ?? 0) + ($this->eliminados ?? 0),
            'exitosos' => ($this->nuevos ?? 0) + ($this->actualizados ?? 0),
            'preview_data' => $this->preview_data,
            'errores_detalle' => $this->errores_detalle,
            'progreso' => $this->progreso,
            'progreso_formatted' => $this->progreso ? number_format($this->progreso, 1).'%' : null,
            'inicio_proceso' => $this->inicio_proceso?->format('Y-m-d H:i:s'),
            'fin_proceso' => $this->fin_proceso?->format('Y-m-d H:i:s'),
            'duracion_segundos' => $this->getDuracionSegundos(),
            'duracion_formatted' => $this->getDuracionFormatted(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at_human' => $this->updated_at->diffForHumans(),

            // Relaciones
            'proveedor' => $this->whenLoaded('proveedor', fn () => [
                'id' => $this->proveedor->id,
                'nombre' => $this->proveedor->nombre,
                'logo' => $this->proveedor->logo,
            ]),

            // Estadísticas calculadas
            'stats' => [
                'success_rate' => $this->getSuccessRate(),
                'error_rate' => $this->getErrorRate(),
                'has_preview' => ! empty($this->preview_data),
                'has_errors' => ($this->errores ?? 0) > 0,
                'is_completed' => $this->estado === 'completed',
                'is_failed' => $this->estado === 'failed',
                'is_processing' => in_array($this->estado, ['pending', 'processing']),
            ],
        ];
    }

    /**
     * Get tipo label
     */
    private function getTipoLabel(): string
    {
        return match ($this->tipo) {
            'productos' => 'Productos',
            'marcas' => 'Marcas',
            'categorias' => 'Categorías',
            'mixed' => 'Mixta',
            default => ucfirst($this->tipo)
        };
    }

    /**
     * Get estado label
     */
    private function getEstadoLabel(): string
    {
        return match ($this->estado) {
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'completed' => 'Completado',
            'failed' => 'Falló',
            'cancelled' => 'Cancelado',
            default => ucfirst($this->estado)
        };
    }

    /**
     * Get estado color
     */
    private function getEstadoColor(): string
    {
        return match ($this->estado) {
            'pending' => 'warning',
            'processing' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'medium',
            default => 'medium'
        };
    }

    /**
     * Get fase label
     */
    private function getFaseLabel(): string
    {
        return match ($this->fase) {
            'upload' => 'Subida',
            'validation' => 'Validación',
            'processing' => 'Procesamiento',
            'cleanup' => 'Limpieza',
            'completed' => 'Completado',
            default => ucfirst($this->fase ?? '')
        };
    }

    /**
     * Get duration in seconds
     */
    private function getDuracionSegundos(): ?int
    {
        if (! $this->inicio_proceso || ! $this->fin_proceso) {
            return null;
        }

        return $this->fin_proceso->diffInSeconds($this->inicio_proceso);
    }

    /**
     * Get formatted duration
     */
    private function getDuracionFormatted(): ?string
    {
        $duracion = $this->getDuracionSegundos();
        if (! $duracion) {
            return null;
        }

        return gmdate('H:i:s', $duracion);
    }

    /**
     * Get success rate percentage
     */
    private function getSuccessRate(): ?float
    {
        if (! $this->total_registros || $this->total_registros == 0) {
            return null;
        }

        $exitosos = ($this->nuevos ?? 0) + ($this->actualizados ?? 0);

        return round(($exitosos / $this->total_registros) * 100, 2);
    }

    /**
     * Get error rate percentage
     */
    private function getErrorRate(): ?float
    {
        if (! $this->total_registros || $this->total_registros == 0) {
            return null;
        }

        return round((($this->errores ?? 0) / $this->total_registros) * 100, 2);
    }
}
