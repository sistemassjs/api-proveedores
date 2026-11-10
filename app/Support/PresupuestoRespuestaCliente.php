<?php

namespace App\Support;

use App\Models\Presupuesto;
use App\Models\User;
use App\Notifications\Presupuesto\PresupuestoAceptadoNotification;
use App\Notifications\Presupuesto\PresupuestoRechazadoNotification;
use Illuminate\Notifications\Notification;

/**
 * Aceptar / rechazar presupuesto por el cliente (público o autenticado).
 */
class PresupuestoRespuestaCliente
{
    public static function aceptar(Presupuesto $presupuesto, ?int $userId = null): Presupuesto
    {
        $estadoAnterior = $presupuesto->estado;
        $presupuesto->estado = Presupuesto::ESTADO_ACEPTADO;
        $presupuesto->save();
        $presupuesto->registrarCambioEstado($estadoAnterior, $userId);

        self::notificarCreadorUnaSolaVez(
            $presupuesto,
            new PresupuestoAceptadoNotification($presupuesto),
            PresupuestoAceptadoNotification::class
        );

        return $presupuesto->fresh(Presupuesto::eagerLodable());
    }

    public static function rechazar(
        Presupuesto $presupuesto,
        ?string $motivo = null,
        ?int $userId = null
    ): Presupuesto {
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
            $userId,
            null,
            $motivoTrim !== '' ? $motivoTrim : null
        );

        self::notificarCreadorUnaSolaVez(
            $presupuesto,
            new PresupuestoRechazadoNotification($presupuesto, $motivoTrim !== '' ? $motivoTrim : null),
            PresupuestoRechazadoNotification::class
        );

        return $presupuesto->fresh(Presupuesto::eagerLodable());
    }

    private static function notificarCreadorUnaSolaVez(
        Presupuesto $presupuesto,
        Notification $notification,
        string $notificationClass
    ): void {
        $destinatario = self::resolverUsuarioNotificarEmisorPresupuesto($presupuesto);
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

    private static function resolverUsuarioNotificarEmisorPresupuesto(Presupuesto $presupuesto): ?User
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
