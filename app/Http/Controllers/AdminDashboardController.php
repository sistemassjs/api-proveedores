<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnumerate;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\PagoSPP;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\SolicitudPago;
use App\Models\Sucursal;
use App\Models\TipoEmpresa;
use App\Models\UnidadMedida;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/dashboard/stats",
     *     summary="Obtener estadísticas completas del dashboard administrativo",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas completas",
     *         @OA\JsonContent(
     *             @OA\Property(property="catalogos", type="object"),
     *             @OA\Property(property="usuarios", type="object"),
     *             @OA\Property(property="pedidos", type="object"),
     *             @OA\Property(property="metricas", type="object")
     *         )
     *     )
     * )
     * Obtiene estadísticas completas del dashboard administrativo
     */
    public function getStatsCompletas(Request $request)
    {
        try {
            $stats = [
                'catalogos' => $this->getCatalogosStats(),
                'usuarios' => $this->getUsuariosStats(),
                'pedidos' => $this->getPedidosStats(),
                'metricas' => $this->calcularMetricasRendimiento(),
            ];

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Estadísticas obtenidas correctamente',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Error al obtener estadísticas: '.$e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Obtiene métricas de rendimiento
     */
    public function getMetricasRendimiento(Request $request)
    {
        try {
            $metricas = $this->calcularMetricasRendimiento();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Métricas obtenidas correctamente',
                'data' => $metricas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Error al obtener métricas: '.$e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Obtiene métricas SPP y presupuesto por proveedor.
     */
    public function getMetricasSppPorProveedor(Request $request)
    {
        try {
            $limite = max(1, (int) $request->input('limit', 50));

            $proveedores = Proveedor::query()
                ->select(['id', 'nombre_comercial', 'razon_social', 'rfc'])
                ->withCount([
                    'solicitudesPago as spp_total',
                    'solicitudesPago as spp_pagadas' => fn ($q) => $q->where('estado_solicitud', 'pagado'),
                ])
                ->withSum('solicitudesPago as spp_monto_total', 'monto_total')
                ->withSum('solicitudesPago as spp_monto_abonado', 'monto_abonado')
                ->withSum('pagosSPP as pagos_spp_monto_total', 'monto_total')
                ->orderByDesc('spp_monto_total')
                ->limit($limite)
                ->get()
                ->map(function (Proveedor $proveedor) {
                    $montoTotal = (float) ($proveedor->spp_monto_total ?? 0);
                    $montoAbonado = (float) ($proveedor->spp_monto_abonado ?? 0);
                    $montoPagosSpp = (float) ($proveedor->pagos_spp_monto_total ?? 0);
                    $saldo = max(0, $montoTotal - $montoAbonado);

                    return [
                        'proveedor_id' => $proveedor->id,
                        'nombre_comercial' => $proveedor->nombre_comercial,
                        'razon_social' => $proveedor->razon_social,
                        'rfc' => $proveedor->rfc,
                        'spp_total' => (int) ($proveedor->spp_total ?? 0),
                        'spp_pagadas' => (int) ($proveedor->spp_pagadas ?? 0),
                        'spp_pendientes' => max(0, (int) ($proveedor->spp_total ?? 0) - (int) ($proveedor->spp_pagadas ?? 0)),
                        'monto_spp_total' => round($montoTotal, 2),
                        'monto_spp_abonado' => round($montoAbonado, 2),
                        'saldo_pendiente' => round($saldo, 2),
                        'monto_pagos_spp' => round($montoPagosSpp, 2),
                        'avance_pago_porcentaje' => $montoTotal > 0 ? round(($montoAbonado / $montoTotal) * 100, 2) : 0,
                    ];
                });

            $resumen = [
                'proveedores_con_spp' => $proveedores->where('spp_total', '>', 0)->count(),
                'spp_total' => SolicitudPago::query()->count(),
                'spp_pagadas' => SolicitudPago::query()->where('estado_solicitud', 'pagado')->count(),
                'monto_spp_total' => round((float) SolicitudPago::query()->sum('monto_total'), 2),
                'monto_pagado_total' => round((float) SolicitudPago::query()->sum('monto_abonado'), 2),
                'monto_pagos_spp_total' => round((float) PagoSPP::query()->sum('monto_total'), 2),
            ];

            $resumen['saldo_pendiente_total'] = round(max(0, $resumen['monto_spp_total'] - $resumen['monto_pagado_total']), 2);

            return $this->success([
                'resumen' => $resumen,
                'proveedores' => $proveedores->values(),
            ], 'Métricas SPP por proveedor obtenidas correctamente.');
        } catch (\Throwable $e) {
            return $this->error('Error al obtener métricas SPP por proveedor.', [
                'exception' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene resumen de catálogos
     */
    public function getCatalogosResumen(Request $request)
    {
        try {
            $catalogos = $this->getCatalogosStats();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Resumen de catálogos obtenido correctamente',
                'data' => $catalogos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Error al obtener resumen de catálogos: '.$e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Estadísticas de catálogos
     */
    private function getCatalogosStats()
    {
        return [
            'usuarios' => User::count(),
            'proveedores' => Proveedor::count(),
            'productos' => Producto::count(),
            'categorias' => Categoria::count(),
            'marcas' => Marca::count(),
            'sucursales' => Sucursal::count(),
            'unidadesMedida' => UnidadMedida::count(),
            'tiposEmpresa' => TipoEmpresa::count(),
        ];
    }

    /**
     * Estadísticas de usuarios
     */
    private function getUsuariosStats()
    {
        $total = User::count();
        $activos = User::where('status', 'activo')->count();

        $roleAdminId = Role::where('nombre', UserRoleEnumerate::ADMINISTRADOR)->first()->id;
        $roleGerenteId = Role::where('nombre', UserRoleEnumerate::GERENTE)->first()->id;
        $roleClienteId = Role::where('nombre', UserRoleEnumerate::CLIENTE)->first()->id;

        return [
            'total' => $total,
            'administradores' => User::where('role_id', $roleAdminId)->count(),
            'proveedores' => User::where('role_id', $roleGerenteId)->count(),
            'clientes' => User::where('role_id', $roleClienteId)->count(),
            'activos' => $activos,
            'inactivos' => $total - $activos,
        ];
    }

    /**
     * Estadísticas de pedidos
     */
    private function getPedidosStats()
    {
        $total = Pedido::count();
        $pendientes = Pedido::where('estatus', 'pendiente')->count();
        $enProceso = Pedido::where('estatus', 'en_proceso')->count();
        $cotizados = Pedido::where('estatus', 'cotizado')->count();
        $entregados = Pedido::where('estatus', 'entregado')->count();
        $cancelados = Pedido::where('estatus', 'cancelado')->count();

        $montoTotal = Pedido::sum('subtotal') ?? 0;
        $montoPromedio = $total > 0 ? $montoTotal / $total : 0;

        return [
            'total' => $total,
            'pendientes' => $pendientes,
            'enProceso' => $enProceso,
            'cotizados' => $cotizados,
            'entregados' => $entregados,
            'cancelados' => $cancelados,
            'montoTotal' => $montoTotal,
            'montoPromedio' => $montoPromedio,
        ];
    }

    private function calcularMetricasRendimiento(): array
    {
        return [
            'visitasHoy' => $this->getVisitasHoy(),
            'visitasSemana' => $this->getVisitasSemana(),
            'visitasMes' => $this->getVisitasMes(),
            'conversionRate' => $this->getConversionRate(),
            'tiempoPromedioRespuesta' => $this->getTiempoPromedioRespuesta(),
            'satisfaccionCliente' => $this->getSatisfaccionCliente(),
            'productosPopulares' => $this->getProductosPopulares(),
            'ventasPorMes' => $this->getVentasPorMes(),
        ];
    }

    /**
     * Obtiene visitas de hoy
     */
    private function getVisitasHoy()
    {
        // Simulación - en producción esto vendría de analytics
        return rand(150, 300);
    }

    /**
     * Obtiene visitas de la semana
     */
    private function getVisitasSemana()
    {
        // Simulación - en producción esto vendría de analytics
        return rand(1000, 2000);
    }

    /**
     * Obtiene visitas del mes
     */
    private function getVisitasMes()
    {
        // Simulación - en producción esto vendría de analytics
        return rand(5000, 10000);
    }

    /**
     * Calcula tasa de conversión
     */
    private function getConversionRate()
    {
        $totalPedidos = Pedido::count();
        $pedidosEntregados = Pedido::where('estatus', 'entregado')->count();

        if ($totalPedidos === 0) {
            return 0;
        }

        return round(($pedidosEntregados / $totalPedidos) * 100, 2);
    }

    /**
     * Obtiene tiempo promedio de respuesta en horas
     */
    private function getTiempoPromedioRespuesta()
    {
        // Simulación - en producción calcular desde created_at hasta primer cambio de estado
        return rand(2, 8);
    }

    /**
     * Obtiene satisfacción del cliente
     */
    private function getSatisfaccionCliente()
    {
        // Simulación - en producción esto vendría de encuestas/reviews
        return round(rand(40, 50) / 10, 1); // Entre 4.0 y 5.0
    }

    /**
     * Obtiene productos más populares
     */
    private function getProductosPopulares()
    {
        // En producción esto vendría de pedido_detalles
        return collect([
            [
                'id' => 1,
                'nombre' => 'Cemento Portland',
                'categoria' => 'Materiales de Construcción',
                'totalPedidos' => 45,
                'montoTotal' => 125000,
            ],
            [
                'id' => 2,
                'nombre' => 'Varilla de Acero #4',
                'categoria' => 'Aceros',
                'totalPedidos' => 38,
                'montoTotal' => 98000,
            ],
            [
                'id' => 3,
                'nombre' => 'Block de Concreto',
                'categoria' => 'Bloques',
                'totalPedidos' => 32,
                'montoTotal' => 76000,
            ],
            [
                'id' => 4,
                'nombre' => 'Arena de Río',
                'categoria' => 'Agregados',
                'totalPedidos' => 28,
                'montoTotal' => 45000,
            ],
            [
                'id' => 5,
                'nombre' => 'Grava Triturada',
                'categoria' => 'Agregados',
                'totalPedidos' => 25,
                'montoTotal' => 38000,
            ],
        ]);
    }

    /**
     * Obtiene ventas por mes (últimos 6 meses)
     */
    private function getVentasPorMes()
    {
        $ventas = collect();

        for ($i = 5; $i >= 0; $i--) {
            $fecha = Carbon::now()->subMonths($i);
            $mes = $fecha->format('M');

            // En producción esto sería:
            // $ventas = Pedido::whereYear('created_at', $fecha->year)
            //     ->whereMonth('created_at', $fecha->month)
            //     ->sum('monto_total');

            $ventasSimuladas = rand(80000, 200000);
            $montoSimulado = rand(2000000, 5000000);

            $ventas->push([
                'mes' => $mes,
                'ventas' => $ventasSimuladas,
                'monto' => $montoSimulado,
            ]);
        }

        return $ventas;
    }
}
