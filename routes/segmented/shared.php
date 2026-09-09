<?php

use App\Http\Controllers\Notifications\DeviceTokenController;
use App\Http\Controllers\CategoriaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\ProductoBusquedaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoImagenController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TipoEmpresaController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\Catalogo\CatalogoPublicoItemController;

/*
|--------------------------------------------------------------------------
| RECURSOS COMPARTIDOS (TODOS LOS ROLES AUTENTICADOS)
|--------------------------------------------------------------------------
| Estas rutas están disponibles para todos los usuarios autenticados
*/

Route::middleware('auth:sanctum')->group(function () {

    /**
     * TIENDA ONLINE
     */
    Route::prefix('tienda')->group(function () {

        /**
         * CATALOGOS PARA LOS FILTROS
         */
        Route::get('proveedores', [ProveedorController::class, 'index'])->middleware(['audit']);
        Route::get('sucursales', [SucursalController::class, 'index'])->middleware(['audit']);
        Route::get('productos', [ProductoController::class, 'index'])->middleware(['audit']);
        Route::get('imagenes', [ProductoImagenController::class, 'index'])->middleware(['audit']);
        Route::get('unidades-medida', [UnidadMedidaController::class, 'index'])->middleware(['audit']);
        Route::get('categorias', [CategoriaController::class, 'index'])->middleware(['audit']);
        Route::get('marcas', [MarcaController::class, 'index'])->middleware(['audit']);
        Route::get('tipos-empresa', [TipoEmpresaController::class, 'index'])->middleware(['audit']);

        /**
         * ACCESSO DIRECTOS
         */
        Route::get('accesos-rapidos', [TiendaController::class, 'accesosRapidos'])->middleware(['audit']);

        Route::prefix('proveedores')->group(function () {
            Route::get('principales', [TiendaController::class, 'proveedoresPrincipales'])->middleware(['audit']);
        });

        Route::prefix('productos')->group(function () {
            Route::get('destacados', [TiendaController::class, 'productosDestacados'])->middleware(['audit']);
            Route::get('mas-pedidos', [TiendaController::class, 'productosMasPedidos'])->middleware(['audit']);
            Route::get('recientes', [TiendaController::class, 'productosRecientes'])->middleware(['audit']);
            Route::get('{producto}', [TiendaController::class, 'show'])->middleware(['audit']);
        });
    });

    /**
     * BÚSQUEDA Y DISPONIBILIDAD DE PRODUCTOS
     */
    Route::prefix('productos')->group(function () {
        Route::get('buscar', [ProductoBusquedaController::class, 'buscar'])->middleware(['audit']);
        Route::get('{producto}/disponibilidad', [ProductoBusquedaController::class, 'verificarDisponibilidad'])->middleware(['audit']);
    });

    /**
     * CATÁLOGO PÚBLICO (lectura; importado por admin)
     */
    Route::prefix('catalogo-publico')->group(function () {
        Route::get('/', [CatalogoPublicoItemController::class, 'index'])->middleware(['audit']);
        Route::get('empresas', [CatalogoPublicoItemController::class, 'empresas'])->middleware(['audit']);
        Route::get('facets', [CatalogoPublicoItemController::class, 'facets'])->middleware(['audit']);
        Route::get('{catalogoPublicoItem}', [CatalogoPublicoItemController::class, 'show'])->middleware(['audit']);
    });


    /**
     * DASHBOARD BÁSICO
     */
    Route::get('dashboard/stats', [DashboardController::class, 'getStats'])->middleware(['audit']);

    /**
     * DEVICE TOKENS (FCM)
     */
    Route::prefix('device-tokens')->group(function () {
        Route::post('/', [DeviceTokenController::class, 'store'])->middleware(['audit']);
        Route::get('/', [DeviceTokenController::class, 'index'])->middleware(['audit']);
        Route::post('/deactivate-current', [DeviceTokenController::class, 'deactivateCurrent'])->middleware(['audit']);
        Route::delete('/{tokenId}', [DeviceTokenController::class, 'deactivate'])->middleware(['audit']);
        Route::post('/cleanup', [DeviceTokenController::class, 'cleanup'])->middleware(['audit']);
        Route::post('/test', [DeviceTokenController::class, 'testNotification'])->middleware(['audit']);
    });

    /**
     * Estado de perfil empresa (lectura): usuarios con relación al proveedor (no solo GERENTE).
     * Registrado aquí para que aplique antes que el grupo exclusivo de gerente.php.
     */
    Route::prefix('proveedores')->group(function () {
        Route::get('{proveedor}/perfil-completado', [ProveedorController::class, 'validarPerfilCompletado'])
            ->middleware(['proveedor.access', 'audit']);
    });
});
