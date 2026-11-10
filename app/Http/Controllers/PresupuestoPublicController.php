<?php

namespace App\Http\Controllers;

use App\Http\Resources\Presupuesto\PresupuestoPublicResource;
use App\Models\Presupuesto;
use App\Support\PresupuestoPdf;
use App\Support\PresupuestoRespuestaCliente;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PresupuestoPublicController extends Controller
{
    use ApiResponse;

    private const MSG_SOLO_EMPRESA = 'Este presupuesto está dirigido a una empresa de la plataforma y solo puede verse o responderse por usuarios de esa empresa en GestionPlus.';

    /**
     * Obtiene el presupuesto por token público (sin autenticación).
     */
    public function show(string $token): JsonResponse
    {
        Presupuesto::actualizarVencidos();

        $presupuesto = Presupuesto::query()
            ->where('token_publico', $token)
            ->with(Presupuesto::eagerLodable())
            ->first();

        if (! $presupuesto) {
            return $this->error('Presupuesto no encontrado o enlace inválido.', null, 404);
        }

        if ($presupuesto->dirigidoAProveedorPlataforma()) {
            return $this->error(self::MSG_SOLO_EMPRESA, [
                'requiere_autenticacion_empresa' => true,
                'presupuesto_id' => (int) $presupuesto->id,
            ], 403);
        }

        $presupuesto->load(Presupuesto::eagerLodable());
        $presupuesto->asegurarTokenPublico();

        return $this->success(
            new PresupuestoPublicResource($presupuesto->fresh(Presupuesto::eagerLodable()))
        );
    }

    /**
     * Descarga el PDF del presupuesto por token.
     */
    public function descargarPdf(string $token): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $presupuesto = Presupuesto::query()
            ->where('token_publico', $token)
            ->with(Presupuesto::eagerLodable())
            ->first();

        if (! $presupuesto) {
            return $this->error('Presupuesto no encontrado o enlace inválido.', null, 404);
        }

        if ($presupuesto->dirigidoAProveedorPlataforma()) {
            return $this->error(self::MSG_SOLO_EMPRESA, [
                'requiere_autenticacion_empresa' => true,
                'presupuesto_id' => (int) $presupuesto->id,
            ], 403);
        }

        try {
            return PresupuestoPdf::generarPdf($presupuesto);
        } catch (\Throwable $e) {
            return $this->error('No fue posible generar el PDF.', [$e->getMessage()], 500);
        }
    }

    /**
     * Acepta el presupuesto (solo si está en estado enviado).
     */
    public function aceptar(string $token): JsonResponse
    {
        $presupuesto = Presupuesto::query()
            ->with(['proveedor', 'user'])
            ->where('token_publico', $token)
            ->first();

        if (! $presupuesto) {
            return $this->error('Presupuesto no encontrado o enlace inválido.', null, 404);
        }

        if ($presupuesto->dirigidoAProveedorPlataforma()) {
            return $this->error(self::MSG_SOLO_EMPRESA, [
                'requiere_autenticacion_empresa' => true,
                'presupuesto_id' => (int) $presupuesto->id,
            ], 403);
        }

        if ($presupuesto->estado !== Presupuesto::ESTADO_ENVIADO) {
            return $this->error(
                'Solo se puede aceptar un presupuesto que esté en estado enviado.',
                ['estado_actual' => $presupuesto->estado],
                422
            );
        }

        $fresh = PresupuestoRespuestaCliente::aceptar($presupuesto, auth()->id());

        return $this->success(
            new PresupuestoPublicResource($fresh),
            'Presupuesto aceptado correctamente.'
        );
    }

    /**
     * Rechaza el presupuesto (solo si está en estado enviado).
     */
    public function rechazar(Request $request, string $token): JsonResponse
    {
        $presupuesto = Presupuesto::query()
            ->with(['proveedor', 'user'])
            ->where('token_publico', $token)
            ->first();

        if (! $presupuesto) {
            return $this->error('Presupuesto no encontrado o enlace inválido.', null, 404);
        }

        if ($presupuesto->dirigidoAProveedorPlataforma()) {
            return $this->error(self::MSG_SOLO_EMPRESA, [
                'requiere_autenticacion_empresa' => true,
                'presupuesto_id' => (int) $presupuesto->id,
            ], 403);
        }

        if ($presupuesto->estado !== Presupuesto::ESTADO_ENVIADO) {
            return $this->error(
                'Solo se puede rechazar un presupuesto que esté en estado enviado.',
                ['estado_actual' => $presupuesto->estado],
                422
            );
        }

        $validator = Validator::make($request->all(), [
            'motivo' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return $this->error('Datos inválidos.', $validator->errors()->all(), 422);
        }

        $fresh = PresupuestoRespuestaCliente::rechazar(
            $presupuesto,
            $request->input('motivo'),
            $request->user()?->id
        );

        return $this->success(
            new PresupuestoPublicResource($fresh),
            'Presupuesto rechazado.'
        );
    }
}
