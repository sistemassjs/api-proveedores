<?php

namespace App\Http\Controllers;

use App\Http\Requests\CuentaBancaria\CuentaBancariaStoreRequest;
use App\Http\Requests\CuentaBancaria\UpdateCuentaBancariaRequest;
use App\Http\Resources\CuentaBancaria\CuentaBancariaResource;
use App\Models\CuentaBancaria;
use App\Models\Proveedor;
use App\Services\Proveedor\ProveedorPerfilCompletadoService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedorCuentaBancariaController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProveedorPerfilCompletadoService $perfilCompletadoService,
    ) {
    }

    /**
     * Lista cuentas bancarias del proveedor con filtros, ordenamiento y paginación
     */
    public function index(Request $request, Proveedor $proveedor)
    {
        $fields = CuentaBancaria::getFilters();
        $filters = $request->only($fields);

        $sortBy = $request->input('sort_by', 'alias'); // Default sort by 'nombre_comercial'
        $order = $request->input('order', 'asc');
        $perPage = $request->input('per_page', 10);

        $originalPaginator = $proveedor->cuentasBancarias()
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        $data = CuentaBancariaResource::collection($originalPaginator)->resolve();

        return $this->paginated($originalPaginator->setCollection(collect($data)));
    }

    /**
     * Obtiene las cuentas bancarias preferidas del proveedor
     */
    public function getPreferida(Proveedor $proveedor)
    {
        $cuentasPreferidas = $proveedor->cuentasBancarias()
            ->where('preferida', true)
            ->where('estatus', 1) // Solo cuentas activas
            ->get();

        if ($cuentasPreferidas->isEmpty()) {
            return $this->error('No hay cuentas bancarias preferidas para este proveedor.', 404);
        }

        return $this->success(
            CuentaBancariaResource::collection($cuentasPreferidas),
            'Cuentas bancarias preferidas obtenidas correctamente'
        );
    }

    /**
     * Establece una o varias cuentas bancarias como preferidas
     */
    public function setPreferida(Request $request, Proveedor $proveedor)
    {
        $request->validate([
            'cuenta_ids' => 'required|array|min:1',
            'cuenta_ids.*' => 'required|integer|exists:cuentas_bancarias,id',
        ]);

        $cuentaIds = $request->input('cuenta_ids');

        // Verificar que todas las cuentas pertenecen al proveedor
        $cuentasDelProveedor = $proveedor->cuentasBancarias()
            ->whereIn('id', $cuentaIds)
            ->pluck('id')
            ->toArray();

        if (count($cuentasDelProveedor) !== count($cuentaIds)) {
            return $this->error('Una o más cuentas bancarias no pertenecen al proveedor.', 403);
        }

        // Desmarcar todas las cuentas como preferidas
        $proveedor->cuentasBancarias()->update(['preferida' => false]);

        // Marcar las cuentas seleccionadas como preferidas
        $proveedor->cuentasBancarias()
            ->whereIn('id', $cuentaIds)
            ->update(['preferida' => true]);

        // Obtener las cuentas actualizadas
        $cuentasPreferidas = $proveedor->cuentasBancarias()
            ->whereIn('id', $cuentaIds)
            ->get();

        return $this->success(
            CuentaBancariaResource::collection($cuentasPreferidas),
            'Cuentas bancarias preferidas establecidas correctamente.'
        );
    }

    /**
     * Muestra una cuenta bancaria específica del proveedor
     */
    public function show(Proveedor $proveedor, CuentaBancaria $cuenta)
    {
        return $this->success(
            new CuentaBancariaResource($cuenta),
            'Cuenta bancaria obtenida correctamente'
        );
    }

    /**
     * Crea una nueva cuenta bancaria del proveedor
     */
    public function store(CuentaBancariaStoreRequest $request, Proveedor $proveedor)
    {
        $data = $request->validated();

        // 🔹 Verificar si es la primera cuenta bancaria del proveedor
        $esPrimeraCuenta = !$proveedor->cuentasBancarias()->exists();

        if ($esPrimeraCuenta) {
            $data['preferida'] = true;
        } else {
            // Si no es la primera, por defecto no es preferida
            $data['preferida'] = false;
        }

        $cuenta = $proveedor->cuentasBancarias()->create($data);
        $this->perfilCompletadoService->sincronizarBandera($proveedor);

        return $this->success(
            new CuentaBancariaResource($cuenta),
            'Cuenta bancaria creada exitosamente',
            201
        );
    }

    /**
     * Actualiza una cuenta bancaria del proveedor
     */
    public function update(
        Proveedor $proveedor,
        CuentaBancaria $cuenta,
        UpdateCuentaBancariaRequest $request
    ): JsonResponse {
        if ($cuenta->proveedor_id !== $proveedor->id) {
            return $this->error('La cuenta bancaria no pertenece al proveedor.', 403);
        }

        // Si se marca como preferida, desmarcamos las demás
        if ($request->filled('preferida') && $request->preferida) {
            $proveedor->cuentasBancarias()
                ->where('id', '!=', $cuenta->id)
                ->update(['preferida' => false]);
        }

        $cuenta->fill($request->validated());
        $cuenta->save();
        $this->perfilCompletadoService->sincronizarBandera($proveedor);

        return $this->success(
            new CuentaBancariaResource($cuenta),
            'Cuenta bancaria actualizada correctamente.'
        );
    }

    /**
     * Elimina una cuenta bancaria del proveedor
     */
    public function destroy(Proveedor $proveedor, CuentaBancaria $cuenta)
    {
        $cuenta->delete();
        $this->perfilCompletadoService->sincronizarBandera($proveedor);

        return $this->success('Cuenta bancaria eliminada exitosamente');
    }
}
