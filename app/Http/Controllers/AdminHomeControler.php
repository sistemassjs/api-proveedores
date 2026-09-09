<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\User;
use App\Support\MetricasPlataforma;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminHomeControler extends Controller
{
    use ApiResponse;

    /**
     * Endpoint principal del dashboard administrativo.
     * Retorna en una sola llamada:
     *  - Totales generales (usuarios registrados, empresas/proveedores registrados)
     *  - Usuarios activos en los últimos 15 días
     *  - Promedio de presupuesto por usuario (últimos 15 días)
     *  - Promedio de SPP por usuario (últimos 15 días)
     *  - Serie diaria de los últimos 15 días (usuarios activos, presupuestos, SPP)
     *
     * Excluye roles de gestión/integración y cuentas/empresas de pruebas.
     */
    public function dashboardDatos(Request $request)
    {
        $dias = (int) $request->input('dias', 15);
        $dias = max(1, min($dias, 90)); // entre 1 y 90
        $fechaLimite = Carbon::now()->subDays($dias)->startOfDay();

        return $this->success([
            'periodo_dias'          => $dias,
            'fecha_desde'           => $fechaLimite->toDateString(),
            'fecha_hasta'           => Carbon::now()->toDateString(),
            'totales'               => $this->getTotalesGenerales(),
            'usuarios_activos'      => $this->getUsuariosActivosDatos($fechaLimite),
            'promedio_presupuesto'  => $this->getPromedioPresupuestoPorUsuario($fechaLimite),
            'promedio_spp'          => $this->getPromedioSppPorUsuario($fechaLimite),
            'serie_diaria'          => $this->getSerieDiaria($fechaLimite, $dias),
        ]);
    }

    /**
     * Endpoint legado — métricas home (catálogos + actividad 7 días).
     */
    public function metricasHome(Request $request)
    {
        $metricas = $this->getMetricasOptimizadas();
        return $this->success($metricas);
    }

    /**
     * Endpoint legado — resumen de catálogos.
     */
    public function getCatalogosCountItems(Request $request)
    {
        $catalogos = [
            [
                'name'  => 'Proveedores',
                'count' => Proveedor::withoutGlobalScope('solo_activos')->paraMetricasPlataforma()->count(),
                'route' => '/pages/panel-admin/proveedores',
                'icon'  => 'briefcase',
            ],
            [
                'name'  => 'Usuarios',
                'count' => User::paraMetricasPlataforma()->count(),
                'route' => '/pages/panel-admin/usuarios',
                'icon'  => 'people',
            ],
        ];

        return $this->success($catalogos);
    }

    /**
     * Endpoint legado — usuarios activos (método público requerido por ruta).
     * Acepta el parámetro ?dias=N (por defecto 15).
     */
    public function getUsuariosActivosEndpoint(Request $request)
    {
        $dias = (int) $request->input('dias', 15);
        $dias = max(1, min($dias, 90));
        $fechaLimite = Carbon::now()->subDays($dias)->startOfDay();

        $datos = $this->getUsuariosActivosDatos($fechaLimite);

        return $this->success([
            'activos'    => $datos['total'],
            'total'      => User::paraMetricasPlataforma()->count(),
            'porcentaje' => $datos['porcentaje'],
            'periodo_dias' => $dias,
        ]);
    }

    // =========================================================
    // PRIVADOS — lógica de negocio
    // =========================================================

    /**
     * Totales generales (sin filtro de fecha).
     */
    private function getTotalesGenerales(): array
    {
        return [
            'total_usuarios'  => User::paraMetricasPlataforma()->count(),
            'total_empresas'  => Proveedor::withoutGlobalScope('solo_activos')->paraMetricasPlataforma()->count(),
        ];
    }

    /**
     * Usuarios únicos que tuvieron actividad en el período.
     * Actividad = creó/actualizó presupuesto O creó SPP.
     */
    private function getUsuariosActivosDatos(Carbon $fechaLimite): array
    {
        $qPres = DB::table('presupuestos')
            ->where('updated_at', '>=', $fechaLimite)
            ->whereNotNull('user_id');
        MetricasPlataforma::aplicarExclusionActividadPresupuesto($qPres);
        $idsPresupuestos = $qPres->distinct()->pluck('user_id')->toArray();

        $qSpp = DB::table('solicitudes_pago')
            ->where('updated_at', '>=', $fechaLimite)
            ->whereNotNull('usuario_creador_id');
        MetricasPlataforma::aplicarExclusionActividadSpp($qSpp);
        $idsSpp = $qSpp->distinct()->pluck('usuario_creador_id')->toArray();

        $idsActivos = array_values(array_unique(array_merge($idsPresupuestos, $idsSpp)));
        $totalActivos = count($idsActivos);
        $totalUsuarios = User::paraMetricasPlataforma()->count();

        $porcentaje = $totalUsuarios > 0
            ? round(($totalActivos / $totalUsuarios) * 100, 2) . '%'
            : '0%';

        return [
            'total'       => $totalActivos,
            'porcentaje'  => $porcentaje,
        ];
    }

    /**
     * Promedio de presupuestos por usuario activo en el período.
     */
    private function getPromedioPresupuestoPorUsuario(Carbon $fechaLimite): array
    {
        $q = DB::table('presupuestos')
            ->selectRaw('COUNT(*) as total_presupuestos, COUNT(DISTINCT user_id) as usuarios_con_presupuesto, COALESCE(AVG(total), 0) as promedio_monto')
            ->where('updated_at', '>=', $fechaLimite)
            ->whereNotNull('user_id');
        MetricasPlataforma::aplicarExclusionActividadPresupuesto($q);
        $resultado = $q->first();

        $totalPresupuestos      = (int)   ($resultado->total_presupuestos ?? 0);
        $usuariosCon            = (int)   ($resultado->usuarios_con_presupuesto ?? 0);
        $promedioMonto          = (float) ($resultado->promedio_monto ?? 0);
        $promedioXUsuario       = $usuariosCon > 0 ? round($totalPresupuestos / $usuariosCon, 2) : 0;

        return [
            'total_presupuestos'          => $totalPresupuestos,
            'usuarios_con_presupuesto'    => $usuariosCon,
            'promedio_presupuestos_x_usuario' => $promedioXUsuario,
            'promedio_monto'              => round($promedioMonto, 2),
        ];
    }

    /**
     * Promedio de SPP por usuario activo en el período.
     */
    private function getPromedioSppPorUsuario(Carbon $fechaLimite): array
    {
        $q = DB::table('solicitudes_pago')
            ->selectRaw('COUNT(*) as total_spp, COUNT(DISTINCT usuario_creador_id) as usuarios_con_spp, COALESCE(AVG(monto_total), 0) as promedio_monto')
            ->where('updated_at', '>=', $fechaLimite)
            ->whereNotNull('usuario_creador_id');
        MetricasPlataforma::aplicarExclusionActividadSpp($q);
        $resultado = $q->first();

        $totalSpp         = (int)   ($resultado->total_spp ?? 0);
        $usuariosCon      = (int)   ($resultado->usuarios_con_spp ?? 0);
        $promedioMonto    = (float) ($resultado->promedio_monto ?? 0);
        $promedioXUsuario = $usuariosCon > 0 ? round($totalSpp / $usuariosCon, 2) : 0;

        return [
            'total_spp'               => $totalSpp,
            'usuarios_con_spp'        => $usuariosCon,
            'promedio_spp_x_usuario'  => $promedioXUsuario,
            'promedio_monto'          => round($promedioMonto, 2),
        ];
    }

    /**
     * Serie diaria de los últimos N días.
     * Para cada día: usuarios activos (presupuesto o SPP), presupuestos creados, SPP creadas.
     */
    private function getSerieDiaria(Carbon $fechaLimite, int $dias): array
    {
        $qPres = DB::table('presupuestos')
            ->selectRaw('DATE(updated_at) as fecha, COUNT(*) as total, COUNT(DISTINCT user_id) as usuarios')
            ->where('updated_at', '>=', $fechaLimite);
        MetricasPlataforma::aplicarExclusionActividadPresupuesto($qPres);
        $presupuestosPorDia = $qPres
            ->groupByRaw('DATE(updated_at)')
            ->get()
            ->keyBy('fecha');

        $qSpp = DB::table('solicitudes_pago')
            ->selectRaw('DATE(updated_at) as fecha, COUNT(*) as total, COUNT(DISTINCT usuario_creador_id) as usuarios')
            ->where('updated_at', '>=', $fechaLimite)
            ->whereNotNull('usuario_creador_id');
        MetricasPlataforma::aplicarExclusionActividadSpp($qSpp);
        $sppPorDia = $qSpp
            ->groupByRaw('DATE(updated_at)')
            ->get()
            ->keyBy('fecha');

        $serie = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha     = Carbon::now()->subDays($i)->toDateString();
            $presDia   = $presupuestosPorDia->get($fecha);
            $sppDia    = $sppPorDia->get($fecha);

            $usuariosPresupuesto = (int) ($presDia->usuarios ?? 0);
            $usuariosSpp         = (int) ($sppDia->usuarios ?? 0);

            // Usuarios únicos del día (aproximado — suma sin duplicar)
            // No hacemos UNION en PHP para simplificar; se muestra como "al menos N"
            $usuariosActivos = max($usuariosPresupuesto, $usuariosSpp);

            $serie[] = [
                'fecha'            => $fecha,
                'usuarios_activos' => $usuariosActivos,
                'presupuestos'     => (int) ($presDia->total ?? 0),
                'spp'              => (int) ($sppDia->total ?? 0),
            ];
        }

        return $serie;
    }

    /**
     * Métricas optimizadas para el endpoint legado (7 días).
     */
    private function getMetricasOptimizadas(): array
    {
        $fechaLimite = Carbon::now()->subDays(7)->startOfDay();
        $totales = $this->getTotalesGenerales();
        $activos = $this->getUsuariosActivosDatos($fechaLimite);

        return [
            'catalogos' => [
                [
                    'name'  => 'Proveedores',
                    'count' => $totales['total_empresas'],
                    'route' => '/pages/panel-admin/proveedores',
                    'icon'  => 'briefcase',
                ],
                [
                    'name'  => 'Usuarios',
                    'count' => $totales['total_usuarios'],
                    'route' => '/pages/panel-admin/usuarios',
                    'icon'  => 'people',
                ],
            ],
            'metricas_actividad' => [
                'usuarios_activos_ultimos_7_dias' => $activos['total'],
                'porcentaje_actividad'            => $activos['porcentaje'],
                'fecha_referencia' => Carbon::now()->subDays(7)->toDateString(),
            ],
        ];
    }
}
