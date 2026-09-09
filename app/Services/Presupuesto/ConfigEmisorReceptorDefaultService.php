<?php

namespace App\Services\Presupuesto;

use App\Models\ConfigEmisorReceptorPresupuesto;
use Illuminate\Support\Facades\DB;

class ConfigEmisorReceptorDefaultService
{
    /**
     * Estado para alta: default si no hay otras activas/default del mismo tipo.
     */
    public function resolveEstadoForNewRecord(int $proveedorId, int $tipo, ?int $requestedEstado): int
    {
        $hasAny = ConfigEmisorReceptorPresupuesto::query()
            ->where('proveedor_id', $proveedorId)
            ->where('tipo', $tipo)
            ->whereIn('estado', [
                ConfigEmisorReceptorPresupuesto::ESTADO_ACTIVO,
                ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT,
            ])
            ->exists();

        if (! $hasAny) {
            return ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT;
        }

        if ($requestedEstado === ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT) {
            return ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT;
        }

        return ConfigEmisorReceptorPresupuesto::ESTADO_ACTIVO;
    }

    /**
     * Deja una sola default por proveedor+tipo (excluyendo opcionalmente un id).
     */
    public function ensureSingleDefault(int $proveedorId, int $tipo, int $defaultConfigId): void
    {
        ConfigEmisorReceptorPresupuesto::query()
            ->where('proveedor_id', $proveedorId)
            ->where('tipo', $tipo)
            ->where('id', '!=', $defaultConfigId)
            ->where('estado', ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT)
            ->update(['estado' => ConfigEmisorReceptorPresupuesto::ESTADO_ACTIVO]);
    }

    /**
     * Tras borrar/inactivar la default, promueve la tarjeta activa más antigua.
     */
    public function promoteDefaultIfMissing(int $proveedorId, int $tipo): void
    {
        $hasDefault = ConfigEmisorReceptorPresupuesto::query()
            ->where('proveedor_id', $proveedorId)
            ->where('tipo', $tipo)
            ->where('estado', ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT)
            ->exists();

        if ($hasDefault) {
            return;
        }

        $candidate = ConfigEmisorReceptorPresupuesto::query()
            ->where('proveedor_id', $proveedorId)
            ->where('tipo', $tipo)
            ->where('estado', ConfigEmisorReceptorPresupuesto::ESTADO_ACTIVO)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($candidate) {
            $candidate->update(['estado' => ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT]);
        }
    }

    /**
     * @param  callable(): ConfigEmisorReceptorPresupuesto  $create
     */
    public function createWithDefaultRules(int $proveedorId, int $tipo, ?int $requestedEstado, callable $create): ConfigEmisorReceptorPresupuesto
    {
        return DB::transaction(function () use ($proveedorId, $tipo, $requestedEstado, $create) {
            $estado = $this->resolveEstadoForNewRecord($proveedorId, $tipo, $requestedEstado);

            /** @var ConfigEmisorReceptorPresupuesto $config */
            $config = $create($estado);

            if ($estado === ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT) {
                $this->ensureSingleDefault($proveedorId, $tipo, (int) $config->id);
            }

            return $config->fresh(ConfigEmisorReceptorPresupuesto::eagerLodable());
        });
    }

    public function applyDefaultOnUpdate(
        ConfigEmisorReceptorPresupuesto $config,
        int $newEstado,
    ): void {
        DB::transaction(function () use ($config, $newEstado) {
            $config->update(['estado' => $newEstado]);

            if ($newEstado === ConfigEmisorReceptorPresupuesto::ESTADO_DEFAULT) {
                $this->ensureSingleDefault(
                    (int) $config->proveedor_id,
                    (int) $config->tipo,
                    (int) $config->id,
                );
            }
        });
    }
}
