<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurificadoraPedidoRequest;
use App\Http\Requests\PurificadoraPedidoUpdateRequest;
use App\Http\Resources\PurificadoraPedido\PurificadoraPedidoResource;
use App\Models\PurificadoraPedido;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pedidos de agua — Purificadora Colibrí.
 *
 * Flujo de estados: pendiente (0) → en proceso (1) → completado (2) | cancelado (3) | eliminado (4).
 *
 * Autenticación:
 * - Público: crear pedido (store).
 * - Sanctum: listar, actualizar, marcar en proceso (enlace), completar, cancelar, eliminar.
 *
 * @see PurificadoraPedidoRequest store (camelCase)
 * @see PurificadoraPedidoUpdateRequest actualizar
 * @see PurificadoraPedidoResource respuestas JSON
 */
class PurificadoraPedidoController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/purificadora-pedidos — Bearer Sanctum.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(PurificadoraPedido::getFilters());
        $sortBy = $request->input('sort_by', 'created_at');
        $order = $request->input('order', 'desc');

        $pedidos = PurificadoraPedido::query()
            ->where('estado', '!=', PurificadoraPedido::ESTADO_ELIMINADO)
            ->filter($filters)
            ->orderBy($sortBy, $order)
            ->get();

        return $this->success(
            PurificadoraPedidoResource::collection($pedidos),
            'Pedidos obtenidos correctamente.'
        );
    }

    /**
     * POST /api/purificadora-pedidos — público.
     */
    public function store(PurificadoraPedidoRequest $request): JsonResponse
    {
        $datos = $request->datosPedido();
        $datos['total'] = $this->calcularTotalPedido(
            $datos['cantidad_garrafones'],
            (float) $datos['precio_unitario']
        );

        $pedido = PurificadoraPedido::create($datos);

        return $this->success([
            'pedido' => new PurificadoraPedidoResource($pedido->fresh()),
            'whatsapp_url' => $pedido->urlWhatsappEnlace(),
        ], 'Pedido registrado correctamente.', 201);
    }

    /**
     * PUT /api/purificadora-pedidos/{id}/actualizar — Bearer Sanctum.
     *
     * Actualiza datos del pedido (sin cambiar estado ni fechas de flujo).
     * Recalcula total si cambian cantidadGarrafones o precioUnitario.
     */
    public function actualizar(PurificadoraPedidoUpdateRequest $request, int $id): JsonResponse
    {
        $pedido = PurificadoraPedido::query()
            ->where('estado', '!=', PurificadoraPedido::ESTADO_ELIMINADO)
            ->find($id);

        if ($pedido === null) {
            return $this->error('Pedido no encontrado.', null, 404);
        }

        $datos = $request->datosActualizacion();
        if ($datos === []) {
            return $this->error('No se enviaron campos para actualizar.', null, 422);
        }

        $pedido->fill($datos);

        if (array_key_exists('cantidad_garrafones', $datos) || array_key_exists('precio_unitario', $datos)) {
            $pedido->total = $this->calcularTotalPedido(
                (int) ($datos['cantidad_garrafones'] ?? $pedido->cantidad_garrafones),
                (float) ($datos['precio_unitario'] ?? $pedido->precio_unitario)
            );
        }

        $pedido->save();

        return $this->success(
            new PurificadoraPedidoResource($pedido->fresh()),
            'Pedido actualizado correctamente.'
        );
    }

    /**
     * GET /api/purificadora-pedidos/{id}/marcar-pedido-proceso-whatsapp-enlace — Bearer Sanctum.
     */
    public function marcarPedidoProcesoWhatsappEnlace(int $id): JsonResponse
    {
        $pedido = $this->buscarPedidoActivo($id);
        if ($pedido instanceof JsonResponse) {
            return $pedido;
        }

        if ((int) $pedido->estado !== PurificadoraPedido::ESTADO_PENDIENTE) {
            return $this->error('Solo se puede pasar a en proceso un pedido pendiente.', null, 422);
        }

        $pedido->update([
            'estado' => PurificadoraPedido::ESTADO_EN_PROCESO,
            'en_proceso_fecha' => now(),
        ]);

        return $this->success(
            new PurificadoraPedidoResource($pedido->fresh()),
            'Pedido marcado en proceso (enlace WhatsApp).'
        );
    }

    /**
     * PUT /api/purificadora-pedidos/{id}/completado — Bearer Sanctum.
     */
    public function marcarCompletado(int $id): JsonResponse
    {
        $pedido = $this->buscarPedidoActivo($id);
        if ($pedido instanceof JsonResponse) {
            return $pedido;
        }

        if ((int) $pedido->estado !== PurificadoraPedido::ESTADO_EN_PROCESO) {
            return $this->error('Solo se puede completar un pedido en proceso.', null, 422);
        }

        $pedido->update([
            'estado' => PurificadoraPedido::ESTADO_COMPLETADO,
            'completado_fecha' => now(),
        ]);

        return $this->success(
            new PurificadoraPedidoResource($pedido->fresh()),
            'Pedido marcado como completado.'
        );
    }

    /**
     * PUT /api/purificadora-pedidos/{id}/cancelado — Bearer Sanctum.
     */
    public function marcarCancelado(int $id): JsonResponse
    {
        $pedido = $this->buscarPedidoActivo($id);
        if ($pedido instanceof JsonResponse) {
            return $pedido;
        }

        if (! in_array((int) $pedido->estado, [
            PurificadoraPedido::ESTADO_PENDIENTE,
            PurificadoraPedido::ESTADO_EN_PROCESO,
        ], true)) {
            return $this->error('No se puede cancelar un pedido completado o ya cancelado.', null, 422);
        }

        $pedido->update([
            'estado' => PurificadoraPedido::ESTADO_CANCELADO,
            'cancelado_fecha' => now(),
        ]);

        return $this->success(
            new PurificadoraPedidoResource($pedido->fresh()),
            'Pedido cancelado.'
        );
    }

    /**
     * PUT /api/purificadora-pedidos/{id}/eliminado — Bearer Sanctum.
     */
    public function marcarDelete(int $id): JsonResponse
    {
        $pedido = PurificadoraPedido::query()->find($id);

        if ($pedido === null) {
            return $this->error('Pedido no encontrado.', null, 404);
        }

        $pedido->update([
            'estado' => PurificadoraPedido::ESTADO_ELIMINADO,
            'cancelado_fecha' => now(),
        ]);

        return $this->success(
            new PurificadoraPedidoResource($pedido->fresh()),
            'Pedido eliminado.'
        );
    }

    /**
     * @return PurificadoraPedido|JsonResponse
     */
    private function buscarPedidoActivo(int $id): PurificadoraPedido|JsonResponse
    {
        $pedido = PurificadoraPedido::query()
            ->where('estado', '!=', PurificadoraPedido::ESTADO_ELIMINADO)
            ->find($id);

        if ($pedido === null) {
            return $this->error('Pedido no encontrado.', null, 404);
        }

        return $pedido;
    }

    private function calcularTotalPedido(int $cantidadGarrafones, float $precioUnitario): string
    {
        return bcmul(
            (string) $cantidadGarrafones,
            number_format($precioUnitario, 2, '.', ''),
            2
        );
    }
}
