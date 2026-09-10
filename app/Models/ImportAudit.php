<?php

namespace App\Models;

class ImportAudit extends BaseModel
{
    protected $fillable = [
        'job_id',
        'proveedor_id',
        'options_read_csv',
        'tipo',
        'archivo',
        'formato',
        'plantilla_version',
        'plantilla_fecha',
        'estado',
        'fase',
        'logs',
        'eta_seconds',
        'mem_peak_mb',
        'total_registros',
        'nuevos',
        'actualizados',
        'eliminados',
        'errores',
        'preview_data',
        'errores_detalle',
        'progreso',
        'inicio_proceso',
        'fin_proceso',
        'error_types',
        'processing_time',
        'memory_usage',
        'numero_registros_procesados',
        'marca_imported',
        'marca_errors',
        'marca_total',
        'categoria_imported',
        'categoria_errors',
        'categoria_total',
        'unidad_imported',
        'unidad_errors',
        'unidad_total',
    ];

    protected $casts = [
        'options_read_csv' => 'array',
        'preview_data' => 'array',
        'errores_detalle' => 'array',
        'logs' => 'array',
        'error_types' => 'array',
        'inicio_proceso' => 'datetime',
        'fin_proceso' => 'datetime',
        'plantilla_fecha' => 'date',
        'processing_time' => 'decimal:2',
        'memory_usage' => 'decimal:2',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Get the filterable fields
     */
    public static function getFilters(): array
    {
        return [
            'search',
            'tipo',
            'estado',
            'formato',
            'date_from',
            'date_to',
            'fase',
            'has_errors',
            'min_registros',
            'max_registros',
        ];
    }

    /**
     * Apply filters to query
     */
    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('archivo', 'like', "%{$search}%")
                        ->orWhere('job_id', 'like', "%{$search}%")
                        ->orWhere('tipo', 'like', "%{$search}%")
                        ->orWhereJsonContains('logs', ['message' => $search]);
                });
            })
            ->when($filters['tipo'] ?? null, function ($query, $tipo) {
                $query->where('tipo', $tipo);
            })
            ->when($filters['estado'] ?? null, function ($query, $estado) {
                $query->where('estado', $estado);
            })
            ->when($filters['formato'] ?? null, function ($query, $formato) {
                $query->where('formato', $formato);
            })
            ->when($filters['fase'] ?? null, function ($query, $fase) {
                $query->where('fase', $fase);
            })
            ->when(isset($filters['has_errors']), function ($query) use ($filters) {
                if ($filters['has_errors']) {
                    $query->where('errores', '>', 0);
                } else {
                    $query->where('errores', '=', 0);
                }
            })
            ->when($filters['min_registros'] ?? null, function ($query, $min) {
                $query->where('total_registros', '>=', $min);
            })
            ->when($filters['max_registros'] ?? null, function ($query, $max) {
                $query->where('total_registros', '<=', $max);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            });
    }

    /**
     * Append a timestamped log entry to the logs array
     *
     * @return $this
     */
    public function appendLog(string $message, array $context = [], string $level = 'info'): self
    {
        $logs = $this->logs ?? [];

        $logEntry = [
            'timestamp' => now()->toISOString(),
            'level' => $level,
            'message' => $message,
        ];

        if (! empty($context)) {
            $logEntry['context'] = $context;
        }

        $logs[] = $logEntry;

        $this->logs = $logs;

        return $this;
    }

    /**
     * Get error statistics grouped by type
     */
    public function getErrorStatistics(): array
    {
        $errorDetails = $this->errores_detalle ?? [];
        $errorTypes = [];

        foreach ($errorDetails as $error) {
            $type = $error['error_type'] ?? 'Unknown';

            if (! isset($errorTypes[$type])) {
                $errorTypes[$type] = [
                    'type' => $type,
                    'count' => 0,
                    'percentage' => 0,
                    'examples' => [],
                ];
            }

            $errorTypes[$type]['count']++;

            // Agregar ejemplo si no existe
            if (count($errorTypes[$type]['examples']) < 3) {
                $errorTypes[$type]['examples'][] = [
                    'message' => $error['error'] ?? 'No message',
                    'item' => isset($error['item']) ? array_slice($error['item'], 0, 3, true) : null,
                ];
            }
        }

        // Calcular porcentajes
        $totalErrors = count($errorDetails);
        if ($totalErrors > 0) {
            foreach ($errorTypes as &$errorType) {
                $errorType['percentage'] = round(($errorType['count'] / $totalErrors) * 100, 2);
            }
        }

        return array_values($errorTypes);
    }

    /**
     * Get processing performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $totalRecords = $this->total_registros ?? 0;
        $processingTime = $this->processing_time ?? 0;
        $memoryUsage = $this->memory_usage ?? 0;

        $metrics = [
            'total_records' => $totalRecords,
            'processing_time_seconds' => $processingTime,
            'memory_usage_mb' => $memoryUsage,
            'records_per_second' => 0,
            'memory_per_record_kb' => 0,
            'efficiency_score' => 0,
        ];

        if ($processingTime > 0 && $totalRecords > 0) {
            $metrics['records_per_second'] = round($totalRecords / $processingTime, 2);
            $metrics['memory_per_record_kb'] = round(($memoryUsage * 1024) / $totalRecords, 2);

            // Calcular score de eficiencia (más alto es mejor)
            // Basado en registros por segundo y uso eficiente de memoria
            $rpsScore = min(100, ($metrics['records_per_second'] / 100) * 50); // Max 50 puntos
            $memoryScore = max(0, 50 - ($metrics['memory_per_record_kb'] / 10)); // Max 50 puntos
            $metrics['efficiency_score'] = round($rpsScore + $memoryScore, 1);
        }

        return $metrics;
    }

    /**
     * Get import summary with success rate
     */
    public function getImportSummary(): array
    {
        $total = $this->total_registros ?? 0;
        $nuevos = $this->nuevos ?? 0;
        $actualizados = $this->actualizados ?? 0;
        $errores = $this->errores ?? 0;

        $successful = $nuevos + $actualizados;
        $successRate = $total > 0 ? round(($successful / $total) * 100, 2) : 0;

        return [
            'total_records' => $total,
            'successful_records' => $successful,
            'new_records' => $nuevos,
            'updated_records' => $actualizados,
            'failed_records' => $errores,
            'success_rate' => $successRate,
            'error_rate' => round(100 - $successRate, 2),
            'status' => $this->estado,
            'phase' => $this->fase,
            'progress' => $this->progreso ?? 0,
        ];
    }

    /**
     * Get structured logs with filtering
     */
    public function getStructuredLogs(?string $level = null, ?int $limit = null): array
    {
        $logs = $this->logs ?? [];

        // Filtrar por nivel si se especifica
        if ($level) {
            $logs = array_filter($logs, function ($log) use ($level) {
                return ($log['level'] ?? 'info') === $level;
            });
        }

        // Aplicar límite si se especifica
        if ($limit && count($logs) > $limit) {
            $logs = array_slice($logs, -$limit);
        }

        return array_values($logs);
    }

    /**
     * Check if import has critical errors
     */
    public function hasCriticalErrors(): bool
    {
        $errorTypes = $this->error_types ?? [];

        $criticalTypes = [
            'ErrorException',
            'SQLException',
            'OutOfMemoryError',
            'TimeoutException',
        ];

        return ! empty(array_intersect($criticalTypes, $errorTypes));
    }
}
