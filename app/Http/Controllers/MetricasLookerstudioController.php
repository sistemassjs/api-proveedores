<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\SolicitudPago;
use App\Models\User;
use App\Support\MetricasPlataforma;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class MetricasLookerstudioController extends Controller
{
    use ApiResponse;

    /**
     * Métricas de actividad (últimos 7 días) para Looker Studio.
     * Excluye roles de gestión/integración y cuentas/empresas de pruebas.
     */
    public function metricasLookerstudio(Request $request)
    {
        $fechaLimite = now()->subDays(6);
        $idsUsuariosExcluidos = MetricasPlataforma::idsUsuariosExcluidos();
        $idsProveedoresExcluidos = MetricasPlataforma::idsProveedoresExcluidos();

        // -------------------------
        // UPDATE PROVEEDOR
        // -------------------------
        $update_data = Proveedor::withoutGlobalScopes()
            ->paraMetricasPlataforma()
            ->where('updated_at', '>=', $fechaLimite)
            ->get(['id', 'updated_at'])
            ->map(function ($item) {
                return [
                    'user_id' => optional($item->usuarioPrincipal())->id,
                    'fecha' => $item->updated_at?->format('Y-m-d'),
                ];
            });

        // -------------------------
        // UPDATE USERS
        // -------------------------
        $update_data_users = User::paraMetricasPlataforma()
            ->where('updated_at', '>=', $fechaLimite)
            ->get(['id', 'updated_at'])
            ->map(function ($item) {
                return [
                    'user_id' => $item->id,
                    'fecha' => $item->updated_at?->format('Y-m-d'),
                ];
            });

        // -------------------------
        // CUENTAS BANCARIAS
        // -------------------------
        $cuentas_bancarias = Proveedor::withoutGlobalScopes()
            ->paraMetricasPlataforma()
            ->whereHas('cuentasBancarias', function ($query) use ($fechaLimite) {
                $query->where('updated_at', '>=', $fechaLimite);
            })
            ->get()
            ->map(function ($item) {
                $cuenta = $item->cuentasBancarias()->latest()->first();

                return [
                    'user_id' => optional($item->usuarioPrincipal())->id,
                    'fecha' => $cuenta?->updated_at?->format('Y-m-d'),
                ];
            });

        // -------------------------
        // SPP
        // -------------------------
        $qSpp = SolicitudPago::query()
            ->where('updated_at', '>=', $fechaLimite)
            ->whereNotNull('usuario_creador_id');
        if ($idsUsuariosExcluidos !== []) {
            $qSpp->whereNotIn('usuario_creador_id', $idsUsuariosExcluidos);
        }
        if ($idsProveedoresExcluidos !== []) {
            $qSpp->whereNotIn('proveedor_id', $idsProveedoresExcluidos);
        }
        $spp = $qSpp->get(['usuario_creador_id', 'updated_at'])
            ->map(function ($item) {
                return [
                    'user_id' => $item->usuario_creador_id,
                    'fecha' => $item->updated_at?->format('Y-m-d'),
                ];
            });

        // -------------------------
        // PRESUPUESTOS
        // -------------------------
        $qPres = Presupuesto::query()
            ->where('updated_at', '>=', $fechaLimite)
            ->whereNotNull('user_id');
        if ($idsUsuariosExcluidos !== []) {
            $qPres->whereNotIn('user_id', $idsUsuariosExcluidos);
        }
        if ($idsProveedoresExcluidos !== []) {
            $qPres->whereNotIn('proveedor_id', $idsProveedoresExcluidos);
        }
        $presupuestos = $qPres->get(['user_id', 'updated_at'])
            ->map(function ($item) {
                return [
                    'user_id' => $item->user_id,
                    'fecha' => $item->updated_at?->format('Y-m-d'),
                ];
            });

        // -------------------------
        // UNIFICAR TODO
        // -------------------------
        $acciones = collect()
            ->concat($spp)
            ->concat($presupuestos)
            ->concat($update_data)
            ->concat($update_data_users)
            ->concat($cuentas_bancarias)
            ->filter(fn ($item) => ! empty($item['user_id']) && ! empty($item['fecha']))
            ->reject(fn ($item) => in_array((int) $item['user_id'], $idsUsuariosExcluidos, true))
            ->map(function ($item) {
                return [
                    'user_id' => $item['user_id'],
                    'fecha' => \Carbon\Carbon::parse($item['fecha'])->toDateString(),
                ];
            });

        // -------------------------
        // AGRUPAR Y CONTAR
        // -------------------------
        $result = $acciones
            ->groupBy('fecha')
            ->map(function ($items, $fecha) {
                return [
                    'fecha' => $fecha,
                    'usuarios' => collect($items)->pluck('user_id')->unique()->count(),
                ];
            })
            ->sortBy('fecha')
            ->values();

        // -------------------------
        // GENERAR RANGO FIJO (7 días)
        // -------------------------
        $rangos = collect();

        for ($i = 6; $i >= 0; $i--) {
            $fecha = now()->subDays($i)->toDateString();
            $rangos[$fecha] = 0;
        }

        // -------------------------
        // SOBRESCRIBIR DATOS
        // -------------------------
        foreach ($result as $item) {
            $rangos[$item['fecha']] = $item['usuarios'];
        }

        // -------------------------
        // FORMATO FINAL
        // -------------------------
        $result = collect($rangos)
            ->map(fn ($usuarios, $fecha) => [
                'fecha' => $fecha,
                'usuarios' => $usuarios,
            ])
            ->values();

        return $this->success($result, 'Metricas obtenidas correctamente');
    }
}
