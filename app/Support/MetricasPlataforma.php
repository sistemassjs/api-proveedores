<?php

namespace App\Support;

use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;

/**
 * Helpers para KPIs de plataforma: exclusión por rol + es_cuenta_de_pruebas.
 */
class MetricasPlataforma
{
    private const CACHE_TTL_SECONDS = 60;

    public static function rolesExcluidos(): array
    {
        return config('metricas_plataforma.roles_excluidos', []);
    }

    /**
     * IDs de usuarios que no deben entrar en totales ni en actividad atribuida a usuario.
     *
     * @return list<int>
     */
    public static function idsUsuariosExcluidos(): array
    {
        return Cache::remember('metricas_plataforma.ids_usuarios_excluidos', self::CACHE_TTL_SECONDS, function () {
            return User::query()
                ->excluidosDeMetricasPlataforma()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        });
    }

    /**
     * IDs de proveedores marcados como cuenta de pruebas.
     *
     * @return list<int>
     */
    public static function idsProveedoresExcluidos(): array
    {
        return Cache::remember('metricas_plataforma.ids_proveedores_excluidos', self::CACHE_TTL_SECONDS, function () {
            return Proveedor::withoutGlobalScopes()
                ->where('es_cuenta_de_pruebas', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        });
    }

    /**
     * Filtra actividad de presupuestos: excluye user_id / proveedor_id de pruebas o roles internos.
     */
    public static function aplicarExclusionActividadPresupuesto(QueryBuilder $query, string $userColumn = 'user_id', string $proveedorColumn = 'proveedor_id'): QueryBuilder
    {
        return self::aplicarExclusionActividad($query, $userColumn, $proveedorColumn);
    }

    /**
     * Filtra actividad de SPP: usuario creador + proveedor.
     */
    public static function aplicarExclusionActividadSpp(QueryBuilder $query, string $userColumn = 'usuario_creador_id', string $proveedorColumn = 'proveedor_id'): QueryBuilder
    {
        return self::aplicarExclusionActividad($query, $userColumn, $proveedorColumn);
    }

    public static function aplicarExclusionActividad(QueryBuilder $query, string $userColumn, string $proveedorColumn): QueryBuilder
    {
        $idsUsuarios = self::idsUsuariosExcluidos();
        $idsProveedores = self::idsProveedoresExcluidos();

        if ($idsUsuarios !== []) {
            $query->whereNotIn($userColumn, $idsUsuarios);
        }

        if ($idsProveedores !== []) {
            $query->whereNotIn($proveedorColumn, $idsProveedores);
        }

        return $query;
    }

    /**
     * Invalida caché de IDs (llamar si se cambia es_cuenta_de_pruebas o roles en runtime).
     */
    public static function forgetCache(): void
    {
        Cache::forget('metricas_plataforma.ids_usuarios_excluidos');
        Cache::forget('metricas_plataforma.ids_proveedores_excluidos');
    }
}
