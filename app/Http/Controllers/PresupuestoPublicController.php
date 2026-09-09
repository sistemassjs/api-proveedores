<?php

namespace App\Http\Controllers;

use App\Http\Resources\Presupuesto\PresupuestoPublicResource;
use App\Models\Presupuesto;
use App\Models\User;
use App\Notifications\Presupuesto\PresupuestoAceptadoNotification;
use App\Notifications\Presupuesto\PresupuestoRechazadoNotification;
use App\Support\PresupuestoPdf;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Validator;

class PresupuestoPublicController extends Controller
{
    use ApiResponse;

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

        if ($presupuesto->estado !== Presupuesto::ESTADO_ENVIADO) {
            return $this->error(
                'Solo se puede aceptar un presupuesto que esté en estado enviado.',
                ['estado_actual' => $presupuesto->estado],
                422
            );
        }

        $estadoAnterior = $presupuesto->estado;
        $presupuesto->estado = Presupuesto::ESTADO_ACEPTADO;
        $presupuesto->save();
        $presupuesto->registrarCambioEstado($estadoAnterior, auth()->id());

        $this->notificarCreadorUnaSolaVez($presupuesto, new PresupuestoAceptadoNotification($presupuesto), PresupuestoAceptadoNotification::class);

        return $this->success(
            new PresupuestoPublicResource($presupuesto->fresh(Presupuesto::eagerLodable())),
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

        $motivo = $request->input('motivo');
        $motivoTrim = is_string($motivo) ? trim($motivo) : '';
        $estadoAnterior = $presupuesto->estado;
        $presupuesto->estado = $motivoTrim !== ''
            ? Presupuesto::ESTADO_RECHAZADO_CON_OBSERVACION
            : Presupuesto::ESTADO_RECHAZADO;
        if ($motivoTrim !== '') {
            $presupuesto->motivo_rechazo = $motivoTrim;
        }
        $presupuesto->save();
        $presupuesto->registrarCambioEstado(
            $estadoAnterior,
            $request->user()?->id,
            null,
            $motivoTrim !== '' ? $motivoTrim : null
        );

        $this->notificarCreadorUnaSolaVez($presupuesto, new PresupuestoRechazadoNotification($presupuesto, $motivo), PresupuestoRechazadoNotification::class);

        return $this->success(
            new PresupuestoPublicResource($presupuesto->fresh(Presupuesto::eagerLodable())),
            'Presupuesto rechazado.'
        );
    }

    private function notificarCreadorUnaSolaVez(Presupuesto $presupuesto, Notification $notification, string $notificationClass): void
    {
        $destinatario = $this->resolverUsuarioNotificarEmisorPresupuesto($presupuesto);
        if (! $destinatario) {
            return;
        }

        $yaExiste = $destinatario->notifications()
            ->where('type', $notificationClass)
            ->where('data->presupuesto_id', (int) $presupuesto->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($yaExiste) {
            return;
        }

        $destinatario->notify($notification);

        $notif = $destinatario->notifications()
            ->where('type', $notificationClass)
            ->where('data->presupuesto_id', (int) $presupuesto->id)
            ->latest()
            ->first();

        $presupuesto->addNotification($notif?->id);
    }

    /**
     * Usuario al que notificar en el proveedor emisor: creador del presupuesto si aplica;
     * si no hay user_id (registros viejos), un único usuario activo con acceso al proveedor.
     */
    private function resolverUsuarioNotificarEmisorPresupuesto(Presupuesto $presupuesto): ?User
    {
        $presupuesto->loadMissing(['user', 'proveedor']);

        $creador = $presupuesto->user;
        if (
            $creador
            && method_exists($creador, 'tieneAccesoAProveedor')
            && $creador->tieneAccesoAProveedor((int) $presupuesto->proveedor_id)
        ) {
            return $creador;
        }

        $proveedor = $presupuesto->proveedor;
        if (! $proveedor) {
            return null;
        }

        return $proveedor->usuariosActivos()->get()->unique('id')->first(function ($u) use ($presupuesto) {
            return $u instanceof User
                && method_exists($u, 'tieneAccesoAProveedor')
                && $u->tieneAccesoAProveedor((int) $presupuesto->proveedor_id);
        });
    }
}
