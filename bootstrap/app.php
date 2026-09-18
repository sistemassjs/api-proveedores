<?php

use Illuminate\Console\Scheduling\Schedule;

use App\Http\Middleware\EnsureCategoriaBelongsToProveedor;
use App\Http\Middleware\EnsureMarcaBelongsToProveedor;
use App\Http\Middleware\EnsureProductoBelongsToProveedor;
use App\Http\Middleware\EnsureProveedorCuentaBancariaAccess;
use App\Http\Middleware\EnsureUserBelongsToProveedor;
use App\Http\Middleware\LogApiActions;
use App\Http\Middleware\LogIncomingRequests;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\EnsureProveedorOwnership;
use App\Http\Middleware\EnsureProveedorProductAccess;
use App\Http\Middleware\EnsureSucursalBelongsToProveedor;
use App\Http\Middleware\EnsureUnidadMedidaBelongsToProveedor;
use App\Http\Middleware\ValidateApiAccess;
use App\Http\Middleware\ValidateApiKey;
use App\Http\Middleware\ValidateProveedorRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        channels: __DIR__ . '/../routes/channels.php',
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware globales (todos van con append)
        $middleware->append([
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            LogIncomingRequests::class,
        ]);

        // Alias de middleware (middleware individuales o agrupados)
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'proveedor.user' => EnsureUserBelongsToProveedor::class,
            'proveedor.producto' => EnsureProductoBelongsToProveedor::class,
            'proveedor.categoria' => EnsureCategoriaBelongsToProveedor::class,
            'proveedor.marca' => EnsureMarcaBelongsToProveedor::class,
            'proveedor.unidad' => EnsureUnidadMedidaBelongsToProveedor::class,
            'proveedor.sucursal' => EnsureSucursalBelongsToProveedor::class,
            'proveedor.cuenta' => EnsureProveedorCuentaBancariaAccess::class,
            'proveedor.access' => EnsureProveedorOwnership::class,
            'proveedor.producto.access' => EnsureProveedorProductAccess::class,
            'proveedor.role' => ValidateProveedorRole::class,
            'api.access' => ValidateApiAccess::class,
            'apikey' => ValidateApiKey::class,
            'audit' => LogApiActions::class,
            'proveedor.full' => [
                'auth:sanctum',
                'api.access',
                'proveedor.access',
            ],
            'proveedor.admin' => [
                'auth:sanctum',
                'api.access',
                'proveedor.access',
                'audit',
            ],
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Aquí puedes configurar el manejo de excepciones
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('presupuestos:notificar-cierre-pendiente')->dailyAt('08:00');
    })
    ->create();
