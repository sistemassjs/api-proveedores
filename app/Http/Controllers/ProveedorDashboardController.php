<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCotizacion;
use App\Enums\EstadoGeneral;
use App\Http\Resources\Proveedor\ProveedorDashboardCotizacionResource;
use App\Models\Cotizacion;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProveedorDashboardController extends Controller
{
    use ApiResponse;

    public function getStats(Request $request, Proveedor $proveedor)
    {
        $stats = [
            // 'productos_activos' => $proveedor->productos()->where('estatus', EstadoGeneral::ACTIVO->value)->count(),
            // 'sucursales_activas' => $proveedor->sucursales()->where('estatus', EstadoGeneral::ACTIVO->value)->count(),
            // 'requisiciones_pendientes' => $proveedor->requisiciones()->where('estatus', 'pendiente')->count(),
            // 'requisiciones_mes' => $proveedor->requisiciones()->whereMonth('created_at', now()->month)->count(),

            'usuarios' => $proveedor->usuariosActivos()->count(),
            'productos' => $proveedor->productos()->where('estatus', EstadoGeneral::ACTIVO->value)->count(),
            'categorias' => $proveedor->categorias()->where('estatus', EstadoGeneral::ACTIVO->value)->count(),
            'marcas' => $proveedor->marcas()->where('estatus', EstadoGeneral::ACTIVO->value)->count(),
            'sucursales' => $proveedor->sucursalesActivas()->count(),
            'unidadesMedida' => UnidadMedida::query()->where('estatus', EstadoGeneral::ACTIVO->value)->count(),

        ];

        return $this->success([
            'stats' => $stats,
        ]);
    }

    // public function cotizacionesDashboard(Request $request, Proveedor $proveedor)
    // {
    //     $fields = Cotizacion::getFilters();
    //     $filters = $request->only($fields);

    //     $sortBy = $request->input('sort_by', 'fecha_cotizacion');
    //     $order = $request->input('order', 'desc');
    //     $perPage = $request->input('per_page', 10);

    //     $query = Cotizacion::query()
    //         ->filter($filters)
    //         ->where('proveedor_id', $proveedor->id)
    //         ->orderBy($sortBy, $order);

    //     $paginator = $query->paginate($perPage);

    //     // Transformación con Resource
    //     $data = ProveedorDashboardCotizacionResource::collection($paginator)->resolve();
    //     return $this->paginated($paginator->setCollection(collect($data)));
    // }

    public function cotizacionesDashboard(Request $request, Proveedor $proveedor)
    {
        $fields = Cotizacion::getFilters();
        $filters = $request->only($fields);

        $sortBy = $request->input('sort_by', 'fecha_cotizacion');
        $order = $request->input('order', 'desc');

        // Separar el filtro de estatus de los demás filtros
        $statusFilter = $filters['estatus'] ?? null;
        unset($filters['estatus']);

        // Query base solo con filtros de fecha y otros (sin estatus)
        $baseQuery = Cotizacion::query()
            ->filter($filters)
            ->where('proveedor_id', $proveedor->id);

        // Query para la lista de cotizaciones (incluye filtro de estatus)
        $listQuery = (clone $baseQuery);
        if ($statusFilter) {
            $listQuery->where('estatus', $statusFilter);
        }

        // Obtener cotizaciones para la lista
        $cotizaciones = $listQuery
            ->orderBy($sortBy, $order)
            ->get();

        // Contar por estatus (sin aplicar filtro de estatus)
        $estatusCounts = collect(EstadoCotizacion::values())
            ->mapWithKeys(fn ($estatus) => [
                $estatus => (clone $baseQuery)->where('estatus', $estatus)->count(),
            ])
            ->toArray();

        // Contar total de cotizaciones (sin filtro de estatus)
        $estatusCounts['todas'] = $baseQuery->count();

        $data = [
            'cotizaciones' => ProveedorDashboardCotizacionResource::collection($cotizaciones)->resolve(),
            'estatusCounts' => $estatusCounts,
        ];

        // Retornar colección con counts
        return $this->success($data, 'Cotizaciones con conteo de estatus.');
    }
}
