<?php

namespace App\Console\Commands;

use App\Mail\CompletaRegistroProveedorMail;
use App\Mail\CompletaRegistroUsuarioMail;
use App\Mail\ContactoMail;
use App\Mail\PasswordResetMail;
use App\Mail\PresupuestoEnviadoMail;
use App\Mail\Support\MailOnlyNotification;
use App\Mail\ValidaCorreoProveedorBasicoMail;
use App\Mail\VerifyUpdatedEmailMail;
use App\Models\Cotizacion;
use App\Models\EmpresaConstrucc;
use App\Models\OrdenCompra;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\SolicitudPago;
use App\Models\User;
use App\Notifications\CotizacionCreadaNotification;
use App\Notifications\OrdenCompra\NuevaOrdenCompraNotification;
use App\Notifications\Presupuesto\PresupuestoAceptadoNotification;
use App\Notifications\Presupuesto\PresupuestoCierrePendienteNotification;
use App\Notifications\Presupuesto\PresupuestoEnviadoNotification;
use App\Notifications\Presupuesto\PresupuestoRechazadoNotification;
use App\Notifications\ProveedorEmpresa\ProveedorAsociadoAEmpresaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoAbonadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoComprobanteActualizadoNotification;
use App\Notifications\SolicitudPago\SolicitudPagoFacturaPendienteNotification;
use App\Notifications\SolicitudPago\SolicitudPagoFacturaSubidaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoPagadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoRechazadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoRechazadaSinAutorizacionNotification;
use App\Notifications\SolicitudPago\SolicitudPagoSinFacturaNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Envía todas las plantillas Blade de correo (recursos/views/emails) al destinatario indicado,
 * usando datos reales de la base de datos cuando están disponibles.
 *
 * El comando se registra por descubrimiento automático de Laravel (no requiere Kernel).
 */
class SendAllBladeMailTemplatesCommand extends Command
{
    protected $signature = 'mail:send-all-templates
                            {email? : Correo destino (p. ej. sistemas_sjs@hotmail.com)}
                            {--to= : Correo destino (alternativa al argumento email)}
                            {--force : Obligatorio fuera de entornos local y staging}
                            {--presupuesto-id= : ID de presupuesto para plantillas que lo requieren}
                            {--solicitud-pago-id= : ID de solicitud de pago}
                            {--cotizacion-id= : ID de cotización}
                            {--user-id= : Usuario base para notifiable y datos de prueba}
                            {--orden-compra-id= : ID numérico de fila en ordenes_compra (PK)}';

    protected $description = 'Envía todas las plantillas Blade de correo al destinatario (solo canal mail en notificaciones).';

    /** @var array<int, string> */
    private array $omitidos = [];

    /** @var array<int, string> */
    private array $enviados = [];

    public function handle(): int
    {
        $to = $this->argument('email') ?: $this->option('to');
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Indica un correo válido como argumento o con --to=correo@dominio.com');

            return self::FAILURE;
        }

        if (! in_array(app()->environment(), ['local', 'staging'], true) && ! $this->option('force')) {
            $this->error('En este entorno debes pasar --force para enviar correos masivos de prueba.');

            return self::FAILURE;
        }

        $baseUser = $this->resolveBaseUser();
        $notifiable = $this->buildMailRecipient($baseUser, $to);

        $this->info('Destino: '.$to);
        $this->info('Entorno: '.app()->environment());
        $this->line('Requisitos: variables MAIL_* en .env y acceso a la base de datos.');
        $this->line('Opcional: registros reales (presupuesto, solicitud de pago, cotización, orden de compra, proveedor y empresa construcc) para datos más fieles al flujo real.');
        $this->newLine();

        $presupuesto = $this->resolvePresupuesto();
        $sp = $this->resolveSolicitudPago();
        $cotizacion = $this->resolveCotizacion();
        $orden = $this->resolveOrdenCompra();
        $proveedor = Proveedor::query()->first();
        $empresa = EmpresaConstrucc::query()->first();

        $this->sendMailables($to, $presupuesto, $baseUser);
        $this->sendNotifications($notifiable, $presupuesto, $sp, $cotizacion, $baseUser, $orden, $proveedor, $empresa);
        $this->sendComprobanteSubidoOrphan($to, $notifiable, $sp);

        $this->newLine();
        foreach ($this->enviados as $line) {
            $this->line('<fg=green>✓</> '.$line);
        }
        if ($this->omitidos !== []) {
            $this->newLine();
            $this->warn('Omitidos (faltan datos en BD o error al enviar):');
            foreach ($this->omitidos as $line) {
                $this->line('  · '.$line);
            }
        }

        $this->newLine();
        $this->info('Listo. Revisa la bandeja y la configuración MAIL_* en .env.');

        return $this->omitidos !== [] && $this->enviados === [] ? self::FAILURE : self::SUCCESS;
    }

    private function resolveBaseUser(): User
    {
        if ($this->option('user-id')) {
            $u = User::query()->find((int) $this->option('user-id'));
            if ($u) {
                return $u;
            }
            $this->warn('Usuario --user-id no encontrado; se usa el primero disponible.');
        }

        return User::query()->firstOrFail();
    }

    private function buildMailRecipient(User $base, string $to): User
    {
        $clone = $base->replicate();
        $clone->exists = false;
        $clone->email = $to;
        if (empty($clone->name)) {
            $clone->name = 'Usuario QA';
        }

        return $clone;
    }

    private function resolvePresupuesto(): ?Presupuesto
    {
        try {
            if ($this->option('presupuesto-id')) {
                $p = Presupuesto::query()
                    ->with(Presupuesto::eagerLodable())
                    ->find((int) $this->option('presupuesto-id'));

                return $p ?: null;
            }

            return Presupuesto::query()
                ->with(Presupuesto::eagerLodable())
                ->latest('id')
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveSolicitudPago(): ?SolicitudPago
    {
        try {
            if ($this->option('solicitud-pago-id')) {
                return SolicitudPago::query()->find((int) $this->option('solicitud-pago-id'));
            }

            return SolicitudPago::query()->latest('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveCotizacion(): ?Cotizacion
    {
        try {
            if ($this->option('cotizacion-id')) {
                return Cotizacion::query()
                    ->with(Cotizacion::eagerLodable())
                    ->find((int) $this->option('cotizacion-id'));
            }

            return Cotizacion::query()
                ->with(Cotizacion::eagerLodable())
                ->latest('id')
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveOrdenCompra(): ?OrdenCompra
    {
        try {
            if ($this->option('orden-compra-id')) {
                return OrdenCompra::query()->find((int) $this->option('orden-compra-id'));
            }

            return OrdenCompra::query()->latest('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function spFolio(SolicitudPago $sp): string
    {
        return (string) ($sp->numero_folio_solicitud ?: $sp->folio_sp_consecutivo ?: 'SP-'.$sp->id);
    }

    private function sendMailables(string $to, ?Presupuesto $presupuesto, User $baseUser): void
    {
        $demoUrl = rtrim(config('app.frontend_url', config('app.url')), '/').'/auth/login';

        $this->tryMailable('ContactoMail', function () use ($to) {
            return new ContactoMail(
                'Contacto QA',
                $to,
                '5555555555',
                'Empresa Demo S.A.',
                'Mensaje de prueba para revisión de plantilla de correo.',
                []
            );
        }, $to);

        $this->tryMailable('PasswordResetMail', fn () => new PasswordResetMail($demoUrl, $baseUser->name ?? 'Usuario'), $to);

        $this->tryMailable('CompletaRegistroUsuarioMail', fn () => new CompletaRegistroUsuarioMail($demoUrl), $to);
        $this->tryMailable('CompletaRegistroProveedorMail', fn () => new CompletaRegistroProveedorMail($demoUrl), $to);

        $this->tryMailable('VerifyUpdatedEmailMail', fn () => new VerifyUpdatedEmailMail($demoUrl, $baseUser->name ?? 'Usuario'), $to);

        $this->tryMailable('ValidaCorreoProveedorBasicoMail', fn () => new ValidaCorreoProveedorBasicoMail($demoUrl, 'Empresa Demo'), $to);

        if ($presupuesto) {
            $this->tryMailable('PresupuestoEnviadoMail', function () use ($presupuesto, $demoUrl) {
                return new PresupuestoEnviadoMail(
                    $presupuesto,
                    $demoUrl.'/public/presupuesto/'.$presupuesto->id,
                    $presupuesto->empresa_receptora_nombre ?? 'Cliente demo',
                    true
                );
            }, $to);
        } else {
            $this->omitidos[] = 'PresupuestoEnviadoMail (no hay presupuesto en BD; usa --presupuesto-id=)';
        }
    }

    /**
     * @param  \Illuminate\Notifications\Notification|(\Closure(): \Illuminate\Notifications\Notification)  $notificationOrFactory
     */
    private function tryNotification(string $label, User $notifiable, Notification|\Closure $notificationOrFactory): void
    {
        try {
            $inner = $notificationOrFactory instanceof Notification
                ? $notificationOrFactory
                : $notificationOrFactory();
            NotificationFacade::send($notifiable, new MailOnlyNotification($inner));
            $this->enviados[] = $label.' (notificación, solo mail)';
        } catch (Throwable $e) {
            $this->omitidos[] = $label.': '.$e->getMessage();
        }
    }

    private function tryMailable(string $label, \Closure $factory, string $to): void
    {
        try {
            Mail::to($to)->send($factory());
            $this->enviados[] = $label.' (Mailable)';
        } catch (Throwable $e) {
            $this->omitidos[] = $label.': '.$e->getMessage();
        }
    }

    private function sendNotifications(
        User $notifiable,
        ?Presupuesto $presupuesto,
        ?SolicitudPago $sp,
        ?Cotizacion $cotizacion,
        User $solicitante,
        ?OrdenCompra $orden,
        ?Proveedor $proveedor,
        ?EmpresaConstrucc $empresa
    ): void {
        if ($presupuesto) {
            $this->tryNotification('PresupuestoEnviadoNotification', $notifiable, fn () => new PresupuestoEnviadoNotification($presupuesto));
            $this->tryNotification('PresupuestoAceptadoNotification', $notifiable, fn () => new PresupuestoAceptadoNotification($presupuesto));
            $this->tryNotification('PresupuestoRechazadoNotification', $notifiable, fn () => new PresupuestoRechazadoNotification($presupuesto, 'Motivo de prueba (plantilla QA).'));
            $this->tryNotification('PresupuestoCierrePendienteNotification', $notifiable, fn () => new PresupuestoCierrePendienteNotification($presupuesto));
        } else {
            $msg = 'Notificaciones de presupuesto (sin registro Presupuesto; --presupuesto-id=)';
            $this->omitidos[] = $msg;
        }

        if ($cotizacion) {
            $this->tryNotification('CotizacionCreadaNotification', $notifiable, fn () => new CotizacionCreadaNotification($cotizacion, $solicitante, 'construccion'));
        } else {
            $this->omitidos[] = 'CotizacionCreadaNotification (sin cotización; --cotizacion-id=)';
        }

        if ($orden && $orden->orden_compra_id) {
            $this->tryNotification('NuevaOrdenCompraNotification', $notifiable, fn () => new NuevaOrdenCompraNotification(
                (string) $orden->orden_compra_id,
                (int) $orden->proveedor_id,
                (int) $orden->empresa_id,
                $orden->estatus ?? 'pendiente'
            ));
        } else {
            $this->omitidos[] = 'NuevaOrdenCompraNotification (sin orden de compra; --orden-compra-id=)';
        }

        if ($proveedor && $empresa) {
            $this->tryNotification('ProveedorAsociadoAEmpresaNotification', $notifiable, fn () => new ProveedorAsociadoAEmpresaNotification(
                (int) $proveedor->id,
                (string) ($proveedor->nombre_comercial ?? $proveedor->razon_social ?? 'Proveedor'),
                (int) $empresa->id,
                (string) $empresa->nombre,
                (string) ($empresa->rfc ?? 'XAXX010101000'),
                (int) ($solicitante->id ?: 1),
                (string) ($solicitante->name ?? 'Usuario construcción')
            ));
        } else {
            $this->omitidos[] = 'ProveedorAsociadoAEmpresaNotification (sin proveedor o empresa_construcc en BD)';
        }

        if (! $sp) {
            $this->omitidos[] = 'Notificaciones SolicitudPago (sin solicitud; --solicitud-pago-id=)';

            return;
        }

        $folio = $this->spFolio($sp);
        $pid = (int) $sp->proveedor_id;
        $sid = (int) $sp->id;
        $userIdSp = $sp->usuario_id ? (int) $sp->usuario_id : null;
        $userIdSinFactura = $userIdSp ?? 1;
        $monto = (float) ($sp->monto_total ?? 0);

        try {
            $abonado = $sp->calcularMontoAbonado();
            $restante = $sp->calcularSaldoRestante();
        } catch (Throwable) {
            $abonado = (float) ($sp->monto_abonado ?? 0);
            $restante = (float) ($sp->saldo_pendiente ?? 0);
        }

        $this->tryNotification('SolicitudPagoSinFacturaNotification', $notifiable, fn () => new SolicitudPagoSinFacturaNotification($folio, $sid, $pid, $userIdSinFactura));
        $this->tryNotification('SolicitudPagoAbonadaNotification', $notifiable, fn () => new SolicitudPagoAbonadaNotification(
            $folio,
            $sid,
            $pid,
            max(1.0, $abonado > 0 ? $abonado / 2 : 1000.0),
            $restante > 0 ? $restante : 500.0,
            $userIdSp,
            $abonado > 0 ? $abonado : 1500.0,
            $monto > 0 ? $monto : 5000.0
        ));
        $this->tryNotification('SolicitudPagoPagadaNotification', $notifiable, fn () => new SolicitudPagoPagadaNotification($folio, $sid, $pid, $monto ?: null, $userIdSp));
        $this->tryNotification('SolicitudPagoRechazadaNotification', $notifiable, fn () => new SolicitudPagoRechazadaNotification($folio, $sid, $pid, 'Motivo de rechazo de prueba.', $userIdSp));
        $this->tryNotification('SolicitudPagoRechazadaSinAutorizacionNotification', $notifiable, fn () => new SolicitudPagoRechazadaSinAutorizacionNotification($folio, $sid, $pid, 'Rechazo en verificación (prueba).', $userIdSp));
        $this->tryNotification('SolicitudPagoFacturaPendienteNotification', $notifiable, fn () => new SolicitudPagoFacturaPendienteNotification($folio, $sid, $pid, $monto ?: null, $userIdSp));
        $this->tryNotification('SolicitudPagoFacturaSubidaNotification', $notifiable, fn () => new SolicitudPagoFacturaSubidaNotification(
            $folio,
            $sid,
            $pid,
            $userIdSp,
            $sp->ruta_archivo_factura_pdf,
            $sp->ruta_archivo_factura_xml
        ));
        $this->tryNotification('SolicitudPagoComprobanteActualizadoNotification', $notifiable, fn () => new SolicitudPagoComprobanteActualizadoNotification(
            $folio,
            $sid,
            $pid,
            $userIdSp,
            $sp->ruta_archivo_comprobante_pago,
            'private'
        ));
    }

    private function sendComprobanteSubidoOrphan(string $to, User $notifiable, ?SolicitudPago $sp): void
    {
        if (! $sp) {
            $this->omitidos[] = 'Vista emails.solicitud-pago.comprobante-subido (sin solicitud de pago)';

            return;
        }

        $folio = $this->spFolio($sp);
        $url = rtrim(config('app.frontend_url', config('app.url')), '/').'/pages/proveedor/sp/detalle/'.$sp->id;

        try {
            Mail::send('emails.solicitud-pago.comprobante-subido', [
                'notifiable' => $notifiable,
                'solicitudPagoFolio' => $folio,
                'proveedorId' => (int) $sp->proveedor_id,
                'urlSolicitud' => $url,
            ], function ($message) use ($to, $folio) {
                $message->to($to)->subject('Comprobante subido — solicitud #'.$folio);
            });
            $this->enviados[] = 'emails.solicitud-pago.comprobante-subido (plantilla sin clase; Mail::send)';
        } catch (Throwable $e) {
            $this->omitidos[] = 'comprobante-subido: '.$e->getMessage();
        }
    }
}
