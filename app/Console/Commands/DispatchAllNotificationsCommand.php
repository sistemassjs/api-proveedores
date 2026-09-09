<?php

namespace App\Console\Commands;

use App\Models\Cotizacion;
use App\Models\EmpresaConstrucc;
use App\Models\OrdenCompra;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Role;
use App\Models\SolicitudPago;
use App\Models\User;
use App\Notifications\Auth\CuentaVerificadaNotification;
use App\Notifications\Auth\NewUserNotification;
use App\Notifications\CotizacionCreadaNotification;
use App\Notifications\OrdenCompra\NuevaOrdenCompraNotification;
use App\Notifications\Presupuesto\PresupuestoAceptadoNotification;
use App\Notifications\Presupuesto\PresupuestoCierrePendienteNotification;
use App\Notifications\Presupuesto\PresupuestoEnviadoNotification;
use App\Notifications\Presupuesto\PresupuestoRecibidoClienteProveedorNotification;
use App\Notifications\Presupuesto\PresupuestoRechazadoNotification;
use App\Notifications\ProveedorEmpresa\ProveedorAsociadoAEmpresaNotification;
use App\Notifications\PushNotification;
use App\Notifications\ReverbNotification;
use App\Notifications\SolicitudPago\SolicitudPagoAbonadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoComprobanteActualizadoNotification;
use App\Notifications\SolicitudPago\SolicitudPagoFacturaPendienteNotification;
use App\Notifications\SolicitudPago\SolicitudPagoFacturaSubidaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoPagadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoRechazadaNotification;
use App\Notifications\SolicitudPago\SolicitudPagoRechazadaSinAutorizacionNotification;
use App\Notifications\SolicitudPago\SolicitudPagoSinFacturaNotification;
use App\Notifications\Usuario\UsuarioReasignadoNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Dispara todas las clases de notificación del backend al usuario indicado
 * (broadcast, database, FCM y mail según defina cada notificación).
 *
 * Guía rápida: php artisan notifications:dispatch-all --help
 */
class DispatchAllNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch-all
                            {user_id? : ID del usuario que recibirá las notificaciones}
                            {--user-id= : ID del usuario (alternativa al argumento)}
                            {--force : Obligatorio fuera de entornos local y staging}
                            {--dry-run : Lista qué se enviaría sin disparar nada}
                            {--skip-mail : Evita canal mail anulando temporalmente el email del notifiable}
                            {--only= : Filtra por nombre (varios separados por coma, sin distinguir mayúsculas)}
                            {--delay=0 : Segundos de espera entre cada notificación}
                            {--presupuesto-id= : Presupuesto para notificaciones de presupuesto}
                            {--solicitud-pago-id= : Solicitud de pago para notificaciones SPP}
                            {--cotizacion-id= : Cotización para CotizacionCreadaNotification}
                            {--orden-compra-id= : Fila en ordenes_compra para orden de compra}
                            {--proveedor-id= : Proveedor para notificaciones de usuario/auth}
                            {--list : Muestra la documentación de uso y sale}';

    protected $description = 'Dispara todas las notificaciones definidas en app/Notifications al usuario indicado (QA multicanal).';

    /** @var array<int, string> */
    private array $enviados = [];

    /** @var array<int, string> */
    private array $omitidos = [];

    protected function configure(): void
    {
        parent::configure();

        $this->setHelp($this->usageDocumentation());
    }

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->line($this->usageDocumentation());

            return self::SUCCESS;
        }

        if (! in_array(app()->environment(), ['local', 'staging'], true) && ! $this->option('force')) {
            $this->error('En producción debes usar --force para disparar todas las notificaciones.');

            return self::FAILURE;
        }

        $user = $this->resolveNotifiableUser();
        $ctx = $this->buildContext($user);

        $this->info('Usuario notifiable: '.$user->name.' (ID: '.$user->id.')');
        $this->info('Entorno: '.app()->environment());
        $this->line('Canales: los que declare cada notificación (broadcast, database, fcm, mail).');
        if ($this->option('skip-mail')) {
            $this->warn('Modo --skip-mail: no se intentará enviar correo.');
        }
        $this->newLine();

        $jobs = $this->notificationFactories($ctx);
        $only = $this->parseOnlyFilter();

        if ($only !== []) {
            $jobs = array_values(array_filter(
                $jobs,
                fn (array $job) => $this->matchesOnly($job['label'], $only)
            ));
        }

        if ($jobs === []) {
            $this->warn('No hay notificaciones que coincidan con el filtro --only.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Modo dry-run — notificaciones que se enviarían:');
            foreach ($jobs as $job) {
                $this->line('  · '.$job['label']);
            }

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Disparar '.count($jobs).' notificaciones a '.$user->email.'?', true)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $delay = max(0, (int) $this->option('delay'));
        $skipMail = (bool) $this->option('skip-mail');

        foreach ($jobs as $index => $job) {
            if ($index > 0 && $delay > 0) {
                sleep($delay);
            }
            $this->dispatchJob($user, $job, $skipMail);
        }

        $this->newLine();
        foreach ($this->enviados as $line) {
            $this->line('<fg=green>✓</> '.$line);
        }
        if ($this->omitidos !== []) {
            $this->newLine();
            $this->warn('Omitidas o con error:');
            foreach ($this->omitidos as $line) {
                $this->line('  · '.$line);
            }
        }

        $this->newLine();
        $this->info('Finalizado. Revisa la app, Reverb, tabla notifications y logs.');
        $this->comment('Documentación: php artisan notifications:dispatch-all --list');

        return $this->enviados === [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Texto de ayuda para --help y --list.
     */
    private function usageDocumentation(): string
    {
        return <<<'DOC'
notifications:dispatch-all — Prueba multicanal de notificaciones (QA)

Propósito
  Instancia y envía (notify) las clases en app/Notifications al usuario indicado.
  Cada notificación usa sus canales reales: broadcast (Reverb), database, FCM y/o mail.

Requisitos
  • Base de datos accesible (.env)
  • Para Reverb/FCM: servicios configurados (el comando no los valida)
  • Datos en BD para notificaciones de dominio (presupuesto, SPP, etc.) o IDs por opción

Ejemplos

  # Ver guía completa
  php artisan notifications:dispatch-all --list

  # Simular sin enviar
  php artisan notifications:dispatch-all --dry-run

  # Enviar al usuario 5 (local/staging; pide confirmación)
  php artisan notifications:dispatch-all 5

  # Sin correos (solo app / BD / push)
  php artisan notifications:dispatch-all 5 --skip-mail

  # Subconjunto por nombre de clase
  php artisan notifications:dispatch-all 5 --only=Presupuesto,UsuarioReasignado

  # Esperar 2 s entre cada envío (útil para Reverb)
  php artisan notifications:dispatch-all 5 --delay=2

  # Datos concretos cuando la BD no tiene registros recientes
  php artisan notifications:dispatch-all 5 --presupuesto-id=10 --solicitud-pago-id=20 --cotizacion-id=3 --orden-compra-id=7 --proveedor-id=1

  # Producción (obligatorio --force)
  php artisan notifications:dispatch-all 5 --force --skip-mail

Opciones
  user_id / --user-id     Usuario notifiable (por defecto: primer usuario de la BD)
  --dry-run               Lista notificaciones que se enviarían
  --skip-mail             Anula email del notifiable solo durante el envío
  --only=                 Filtro por fragmento del nombre (coma = varios)
  --delay=                Pausa en segundos entre notificaciones
  --force                 Permite ejecutar fuera de local/staging
  --presupuesto-id=       Presupuesto para el bloque de presupuestos
  --solicitud-pago-id=    Solicitud de pago para notificaciones SPP
  --cotizacion-id=        Cotización para CotizacionCreadaNotification
  --orden-compra-id=      PK en ordenes_compra para NuevaOrdenCompraNotification
  --proveedor-id=         Proveedor para NewUser / UsuarioReasignado

Notificaciones incluidas
  Siempre (si hay datos mínimos):
    PushNotification, ReverbNotification
    NewUserNotification, CuentaVerificadaNotification, UsuarioReasignadoNotification
  Con presupuesto en BD o --presupuesto-id:
    PresupuestoEnviado, Aceptado, Rechazado, CierrePendiente, RecibidoClienteProveedor
  Con cotización o --cotizacion-id:
    CotizacionCreadaNotification
  Con orden de compra o --orden-compra-id:
    NuevaOrdenCompraNotification
  Con proveedor + empresa_construcc:
    ProveedorAsociadoAEmpresaNotification
  Con solicitud de pago o --solicitud-pago-id:
    SolicitudPagoSinFactura, Abonada, Pagada, Rechazada, RechazadaSinAutorizacion,
    FacturaPendiente, FacturaSubida, ComprobanteActualizado

Verificación
  • App: modal / campana de notificaciones (usuario logueado)
  • BD: tabla notifications
  • Reverb: consola del navegador / canal privado del usuario
  • FCM: dispositivo con token activo

Comandos relacionados
  notifications:test          Una sola PushNotification de prueba
  notifications:verify        Comprueba registro de canales FCM/Reverb
  mail:send-all-templates     Plantillas de correo (solo mail, otro flujo)

DOC;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(User $notifiable): array
    {
        $presupuesto = $this->resolvePresupuesto();
        $sp = $this->resolveSolicitudPago();
        $cotizacion = $this->resolveCotizacion();
        $orden = $this->resolveOrdenCompra();
        $proveedor = $this->resolveProveedor();
        $empresa = EmpresaConstrucc::query()->first();
        $otroUsuario = User::query()->where('id', '!=', $notifiable->id)->first() ?? $notifiable;
        $rol = Role::query()->find($otroUsuario->role_id) ?? Role::query()->first();

        return compact(
            'notifiable',
            'presupuesto',
            'sp',
            'cotizacion',
            'orden',
            'proveedor',
            'empresa',
            'otroUsuario',
            'rol'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<int, array{label: string, factory: \Closure(): ?Notification}>
     */
    private function notificationFactories(array $ctx): array
    {
        /** @var User $notifiable */
        $notifiable = $ctx['notifiable'];
        /** @var User $otroUsuario */
        $otroUsuario = $ctx['otroUsuario'];
        /** @var ?Proveedor $proveedor */
        $proveedor = $ctx['proveedor'];
        /** @var ?Presupuesto $presupuesto */
        $presupuesto = $ctx['presupuesto'];
        /** @var ?SolicitudPago $sp */
        $sp = $ctx['sp'];
        /** @var ?Cotizacion $cotizacion */
        $cotizacion = $ctx['cotizacion'];
        /** @var ?OrdenCompra $orden */
        $orden = $ctx['orden'];
        /** @var ?EmpresaConstrucc $empresa */
        $empresa = $ctx['empresa'];
        /** @var ?Role $rol */
        $rol = $ctx['rol'];

        $jobs = [
            [
                'label' => 'PushNotification',
                'factory' => fn () => new PushNotification(
                    'Notificación de prueba (dispatch-all)',
                    'Mensaje generado por notifications:dispatch-all.',
                    'info',
                    ['qa' => true, 'source' => 'dispatch-all'],
                    '/pages/proveedor/home',
                    $notifiable->id
                ),
            ],
            [
                'label' => 'ReverbNotification',
                'factory' => fn () => new ReverbNotification,
            ],
            [
                'label' => 'NewUserNotification',
                'factory' => function () use ($otroUsuario, $proveedor) {
                    if (! $proveedor) {
                        return null;
                    }

                    return new NewUserNotification($otroUsuario, $proveedor);
                },
            ],
            [
                'label' => 'CuentaVerificadaNotification',
                'factory' => fn () => new CuentaVerificadaNotification(
                    $notifiable->email ?? 'qa@example.com',
                    (int) $notifiable->id,
                    now()->toIso8601String()
                ),
            ],
            [
                'label' => 'UsuarioReasignadoNotification',
                'factory' => function () use ($otroUsuario, $proveedor, $rol) {
                    if (! $proveedor) {
                        return null;
                    }

                    return new UsuarioReasignadoNotification(
                        (int) $otroUsuario->id,
                        (string) ($otroUsuario->name ?? 'Usuario QA'),
                        (string) ($otroUsuario->email ?? 'qa@example.com'),
                        (string) ($rol->name ?? 'GERENTE'),
                        'PRINCIPAL',
                        (string) ($proveedor->nombre_comercial ?? 'Empresa demo')
                    );
                },
            ],
        ];

        if ($presupuesto) {
            $jobs[] = ['label' => 'PresupuestoEnviadoNotification', 'factory' => fn () => new PresupuestoEnviadoNotification($presupuesto)];
            $jobs[] = ['label' => 'PresupuestoAceptadoNotification', 'factory' => fn () => new PresupuestoAceptadoNotification($presupuesto)];
            $jobs[] = ['label' => 'PresupuestoRechazadoNotification', 'factory' => fn () => new PresupuestoRechazadoNotification($presupuesto, 'Motivo de prueba (dispatch-all).')];
            $jobs[] = ['label' => 'PresupuestoCierrePendienteNotification', 'factory' => fn () => new PresupuestoCierrePendienteNotification($presupuesto)];
            $jobs[] = ['label' => 'PresupuestoRecibidoClienteProveedorNotification', 'factory' => fn () => new PresupuestoRecibidoClienteProveedorNotification($presupuesto, false)];
        }

        if ($cotizacion) {
            $jobs[] = ['label' => 'CotizacionCreadaNotification', 'factory' => fn () => new CotizacionCreadaNotification($cotizacion, $otroUsuario, 'construccion')];
        }

        if ($orden && $orden->orden_compra_id) {
            $jobs[] = [
                'label' => 'NuevaOrdenCompraNotification',
                'factory' => fn () => new NuevaOrdenCompraNotification(
                    (string) $orden->orden_compra_id,
                    (int) $orden->proveedor_id,
                    (int) $orden->empresa_id,
                    $orden->estatus ?? 'pendiente'
                ),
            ];
        }

        if ($proveedor && $empresa) {
            $jobs[] = [
                'label' => 'ProveedorAsociadoAEmpresaNotification',
                'factory' => fn () => new ProveedorAsociadoAEmpresaNotification(
                    (int) $proveedor->id,
                    (string) ($proveedor->nombre_comercial ?? $proveedor->razon_social ?? 'Proveedor'),
                    (int) $empresa->id,
                    (string) $empresa->nombre,
                    (string) ($empresa->rfc ?? 'XAXX010101000'),
                    (int) ($otroUsuario->id ?: 1),
                    (string) ($otroUsuario->name ?? 'Usuario construcción')
                ),
            ];
        }

        if ($sp) {
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

            $jobs[] = ['label' => 'SolicitudPagoSinFacturaNotification', 'factory' => fn () => new SolicitudPagoSinFacturaNotification($folio, $sid, $pid, $userIdSinFactura)];
            $jobs[] = ['label' => 'SolicitudPagoAbonadaNotification', 'factory' => fn () => new SolicitudPagoAbonadaNotification(
                $folio,
                $sid,
                $pid,
                max(1.0, $abonado > 0 ? $abonado / 2 : 1000.0),
                $restante > 0 ? $restante : 500.0,
                $userIdSp,
                $abonado > 0 ? $abonado : 1500.0,
                $monto > 0 ? $monto : 5000.0
            )];
            $jobs[] = ['label' => 'SolicitudPagoPagadaNotification', 'factory' => fn () => new SolicitudPagoPagadaNotification($folio, $sid, $pid, $monto ?: null, $userIdSp)];
            $jobs[] = ['label' => 'SolicitudPagoRechazadaNotification', 'factory' => fn () => new SolicitudPagoRechazadaNotification($folio, $sid, $pid, 'Motivo de prueba.', $userIdSp)];
            $jobs[] = ['label' => 'SolicitudPagoRechazadaSinAutorizacionNotification', 'factory' => fn () => new SolicitudPagoRechazadaSinAutorizacionNotification($folio, $sid, $pid, 'Rechazo QA.', $userIdSp)];
            $jobs[] = ['label' => 'SolicitudPagoFacturaPendienteNotification', 'factory' => fn () => new SolicitudPagoFacturaPendienteNotification($folio, $sid, $pid, $monto ?: null, $userIdSp)];
            $jobs[] = ['label' => 'SolicitudPagoFacturaSubidaNotification', 'factory' => fn () => new SolicitudPagoFacturaSubidaNotification(
                $folio,
                $sid,
                $pid,
                $userIdSp,
                $sp->ruta_archivo_factura_pdf,
                $sp->ruta_archivo_factura_xml
            )];
            $jobs[] = ['label' => 'SolicitudPagoComprobanteActualizadoNotification', 'factory' => fn () => new SolicitudPagoComprobanteActualizadoNotification(
                $folio,
                $sid,
                $pid,
                $userIdSp,
                $sp->ruta_archivo_comprobante_pago,
                'private'
            )];
        }

        return $jobs;
    }

    /**
     * @param  array{label: string, factory: \Closure(): ?Notification}  $job
     */
    private function dispatchJob(User $user, array $job, bool $skipMail): void
    {
        $label = $job['label'];

        try {
            $notification = ($job['factory'])();
            if (! $notification instanceof Notification) {
                $this->omitidos[] = $label.' (faltan datos en BD; usa opciones --presupuesto-id, --solicitud-pago-id, etc.)';

                return;
            }

            $originalEmail = $user->email;
            if ($skipMail) {
                $user->email = null;
            }

            try {
                $channels = $notification->via($user);
                $user->notify($notification);
                $channelList = implode(', ', array_map(
                    fn ($c) => is_string($c) ? $c : class_basename($c),
                    $channels
                ));
                $this->enviados[] = $label.' ['.$channelList.']';
            } finally {
                if ($skipMail) {
                    $user->email = $originalEmail;
                }
            }
        } catch (Throwable $e) {
            $this->omitidos[] = $label.': '.$e->getMessage();
        }
    }

    private function resolveNotifiableUser(): User
    {
        $id = $this->argument('user_id') ?: $this->option('user-id');
        if ($id) {
            $user = User::query()->find((int) $id);
            if ($user) {
                return $user;
            }
            $this->warn('Usuario no encontrado; se usa el primero de la BD.');
        }

        return User::query()->firstOrFail();
    }

    private function resolveProveedor(): ?Proveedor
    {
        if ($this->option('proveedor-id')) {
            return Proveedor::query()->find((int) $this->option('proveedor-id'));
        }

        return Proveedor::query()->first();
    }

    private function resolvePresupuesto(): ?Presupuesto
    {
        try {
            if ($this->option('presupuesto-id')) {
                return Presupuesto::query()
                    ->with(Presupuesto::eagerLodable())
                    ->find((int) $this->option('presupuesto-id'));
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

    /**
     * @return array<int, string>
     */
    private function parseOnlyFilter(): array
    {
        $raw = trim((string) $this->option('only'));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * @param  array<int, string>  $only
     */
    private function matchesOnly(string $label, array $only): bool
    {
        $haystack = strtolower($label);
        foreach ($only as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
