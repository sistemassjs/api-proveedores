<?php

namespace App\Providers;

use App\Channels\FcmChannel;
use App\Enums\UserRoleEnumerate;
use App\Exceptions\Handler;
use App\Models\Producto;
use App\Models\SolicitudPago;
use App\Models\Sucursal;
use App\Observers\ProductoObserver;
use App\Observers\SolicitudPagoObserver;
use App\Policies\SucursalPolicy;
use App\Services\DashboardService;
use App\Services\ProductoSearchService;
use App\Services\ReporteService;
use App\Services\SucursalService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use App\Support\EmailLogoHelper;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use App\Livewire\Pulse\ErroresGenerales;
use App\Livewire\Pulse\RegistrosDiariosUsuariosProveedores;
use App\Livewire\Pulse\TotalProveedores;
use App\Livewire\Pulse\TotalUsuarios;
use App\Livewire\Pulse\UsuariosProveedores;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Sucursal::class => SucursalPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExceptionHandler::class, Handler::class);

        $this->app->singleton(SucursalService::class, function ($app) {
            return new SucursalService;
        });

        $this->app->singleton(ProductoSearchService::class, function ($app) {
            return new ProductoSearchService;
        });

        $this->app->singleton(ReporteService::class, function ($app) {
            return new ReporteService;
        });

        $this->app->singleton(DashboardService::class, function ($app) {
            return new DashboardService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        // Registrar Observers
        Producto::observe(ProductoObserver::class);
        SolicitudPago::observe(SolicitudPagoObserver::class);


        // Configurar timezone si es necesario
        if (config('app.timezone')) {
            date_default_timezone_set(config('app.timezone'));
        }

        // Forzar raíz de URLs (asset/route) con APP_URL para proxies con path (p. ej. /gestion).
        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            URL::forceRootUrl(rtrim($appUrl, '/'));
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        if (env('APP_ENV') === 'production') {
            $this->app['request']->server->set('HTTPS', true);
        }

        // Registrar canal personalizado FCM
        Notification::extend('fcm', function ($app) {
            return $app->make(FcmChannel::class);
        });

        View::composer('emails.*', function ($view) {
            $view->with('logoAppDataUri', EmailLogoHelper::logoGestionPlusDataUri());
        });

        Gate::define('viewPulse', function ($user = null) {
            if (app()->isLocal()) {
                return true;
            }

            return $user && $user->hasRole(UserRoleEnumerate::ADMINISTRADOR->value);
        });

        Livewire::component('pulse.usuarios-proveedores', UsuariosProveedores::class);
        Livewire::component('pulse.errores-generales', ErroresGenerales::class);
        Livewire::component('pulse.total-usuarios', TotalUsuarios::class);
        Livewire::component('pulse.total-proveedores', TotalProveedores::class);
        Livewire::component('pulse.registros-diarios-usuarios-proveedores', RegistrosDiariosUsuariosProveedores::class);
    }
}
