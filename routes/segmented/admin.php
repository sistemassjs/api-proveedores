<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoImagenController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\TipoEmpresaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\AdminHomeControler;
use App\Http\Controllers\AdminDashboardController;
use App\Enums\UserRoleEnumerate;
use App\Http\Controllers\AdminPedidosController;
use App\Http\Controllers\AdminProveedorController;
use App\Http\Controllers\ProveedorUsuarioController;
use App\Http\Controllers\Admin\ProveedorHomologacionController;
use App\Http\Controllers\Admin\AdminCatalogoPublicoController;

/*
|--------------------------------------------------------------------------
| RUTAS ESPECÍFICAS PARA ROL: ADMINISTRADOR
|--------------------------------------------------------------------------
| Estas rutas solo son accesibles para usuarios con rol ADMINISTRADOR
*/

Route::middleware(['auth:sanctum', 'role:' . UserRoleEnumerate::ADMINISTRADOR->value])->prefix('admin')->group(function () {

    /**
     * GESTIÓN GENERAL DE USUARIOS
     */
    Route::get('usuarios/conteos-listado', [UserController::class, 'conteosListado']);
    Route::apiResource('usuarios', UserController::class);

    /**
     * REASIGNACIÓN DE USUARIOS A PROVEEDORES
     */
    Route::post('usuarios/{user}/reasignar', [ProveedorUsuarioController::class, 'reasignarUsuario']);

    /**
     * GESTIÓN DE CATÁLOGOS MAESTROS
     */
    Route::prefix('catalogos')->group(function () {
        // Proveedores
        Route::get('proveedores', [AdminProveedorController::class, 'index']);
        Route::get('proveedores/conteos-listado', [AdminProveedorController::class, 'conteosListado']);
        Route::post('proveedores', [AdminProveedorController::class, 'store']);
        Route::get('proveedores/{proveedor}/resumen', [AdminProveedorController::class, 'resumen']);
        Route::get('proveedores/{proveedor}/impacto-restriccion-estatus', [AdminProveedorController::class, 'impactoRestriccionEstatus']);
        Route::get('proveedores/{proveedor}', [AdminProveedorController::class, 'show']);
        Route::put('proveedores/{proveedor}', [AdminProveedorController::class, 'update']);
        Route::patch('proveedores/{proveedor}', [AdminProveedorController::class, 'update']);
        Route::delete('proveedores/{proveedor}', [AdminProveedorController::class, 'destroy']);
        Route::get('proveedores/{proveedor}/productos', [AdminProveedorController::class, 'productos']);
        Route::get('proveedores/all/count-categorias', [AdminProveedorController::class, 'proveedoresConCategoriasConSubcatCountProductos']);

        Route::prefix('proveedores/{proveedor}/users')->group(function () {
            Route::get('/', [ProveedorUsuarioController::class, 'index']);
            Route::post('/', [ProveedorUsuarioController::class, 'store']);
            Route::post('/vincular', [ProveedorUsuarioController::class, 'vincularExistente']);
            Route::get('{user}/actividad', [AdminProveedorController::class, 'actividadUsuario']);
            Route::get('{user}', [ProveedorUsuarioController::class, 'show']);
            Route::patch('{user}', [ProveedorUsuarioController::class, 'update']);
            Route::delete('{user}', [ProveedorUsuarioController::class, 'destroy']);
            Route::post('{user}/logo', [ProveedorUsuarioController::class, 'updateLogo']);
            Route::patch('{user}/relacion', [ProveedorUsuarioController::class, 'updateRelacion']);
            Route::patch('{user}/estado', [ProveedorUsuarioController::class, 'cambiarEstado']);
        });

        // Sucursales
        Route::get('sucursales', [SucursalController::class, 'index']);
        Route::post('sucursales', [SucursalController::class, 'store']);
        Route::get('sucursales/groupedByProveedor', [SucursalController::class, 'indexGroupedByProveedor']);
        Route::get('sucursales/{sucursal}', [SucursalController::class, 'show']);
        Route::put('sucursales/{sucursal}', [SucursalController::class, 'update']);
        Route::patch('sucursales/{sucursal}', [SucursalController::class, 'update']);
        Route::delete('sucursales/{sucursal}', [SucursalController::class, 'destroy']);

        // Productos
        Route::get('productos', [ProductoController::class, 'index']);
        Route::post('productos', [ProductoController::class, 'store']);
        Route::get('productos/{producto}', [ProductoController::class, 'show']);
        Route::put('productos/{producto}', [ProductoController::class, 'update']);
        Route::patch('productos/{producto}', [ProductoController::class, 'update']);
        Route::delete('productos/{producto}', [ProductoController::class, 'destroy']);

        // Imágenes
        Route::get('imagenes', [ProductoImagenController::class, 'index']);
        Route::post('imagenes', [ProductoImagenController::class, 'store']);
        Route::get('imagenes/{imagen}', [ProductoImagenController::class, 'show']);
        Route::put('imagenes/{imagen}', [ProductoImagenController::class, 'update']);
        Route::patch('imagenes/{imagen}', [ProductoImagenController::class, 'update']);
        Route::delete('imagenes/{imagen}', [ProductoImagenController::class, 'destroy']);

        // Unidades de medida
        Route::get('unidades-medida', [UnidadMedidaController::class, 'index']);
        Route::post('unidades-medida', [UnidadMedidaController::class, 'store']);
        Route::get('unidades-medida/{unidad_medida}', [UnidadMedidaController::class, 'show']);
        Route::put('unidades-medida/{unidad_medida}', [UnidadMedidaController::class, 'update']);
        Route::patch('unidades-medida/{unidad_medida}', [UnidadMedidaController::class, 'update']);
        Route::delete('unidades-medida/{unidad_medida}', [UnidadMedidaController::class, 'destroy']);

        // Categorías
        Route::get('categorias', [CategoriaController::class, 'index']);
        Route::post('categorias', [CategoriaController::class, 'store']);
        Route::get('categorias/{categoria}', [CategoriaController::class, 'show']);
        Route::put('categorias/{categoria}', [CategoriaController::class, 'update']);
        Route::patch('categorias/{categoria}', [CategoriaController::class, 'update']);
        Route::delete('categorias/{categoria}', [CategoriaController::class, 'destroy']);

        // Marcas
        Route::get('marcas', [MarcaController::class, 'index']);
        Route::post('marcas', [MarcaController::class, 'store']);
        Route::get('marcas/{marca}', [MarcaController::class, 'show']);
        Route::put('marcas/{marca}', [MarcaController::class, 'update']);
        Route::patch('marcas/{marca}', [MarcaController::class, 'update']);
        Route::delete('marcas/{marca}', [MarcaController::class, 'destroy']);

        // Tipos de empresa
        Route::get('tipos-empresa', [TipoEmpresaController::class, 'index']);
        Route::post('tipos-empresa', [TipoEmpresaController::class, 'store']);
        Route::get('tipos-empresa/{tipo_empresa}', [TipoEmpresaController::class, 'show']);
        Route::put('tipos-empresa/{tipo_empresa}', [TipoEmpresaController::class, 'update']);
        Route::patch('tipos-empresa/{tipo_empresa}', [TipoEmpresaController::class, 'update']);
        Route::delete('tipos-empresa/{tipo_empresa}', [TipoEmpresaController::class, 'destroy']);
    });

    /**
     * CATÁLOGO PÚBLICO (feed importado por admin; no es el catálogo NextPro)
     */
    Route::prefix('catalogo-publico')->group(function () {
        Route::post('import', [AdminCatalogoPublicoController::class, 'import']);
        Route::get('/', [AdminCatalogoPublicoController::class, 'index']);
        Route::get('empresas', [AdminCatalogoPublicoController::class, 'empresas']);
        Route::patch('empresas', [AdminCatalogoPublicoController::class, 'updateEmpresa']);
        Route::delete('empresas', [AdminCatalogoPublicoController::class, 'destroyEmpresa']);
        Route::get('facets', [AdminCatalogoPublicoController::class, 'facets']);
        Route::get('{catalogoPublicoItem}', [AdminCatalogoPublicoController::class, 'show']);
        Route::patch('{catalogoPublicoItem}', [AdminCatalogoPublicoController::class, 'update']);
        Route::put('{catalogoPublicoItem}', [AdminCatalogoPublicoController::class, 'update']);
        Route::delete('{catalogoPublicoItem}', [AdminCatalogoPublicoController::class, 'destroy']);
    });

    /**
     * GESTIÓN ADMINISTRATIVA DE PEDIDOS
     */
    // Route::prefix('pedidos')->group(function () {
    //     Route::get('/', [AdminPedidosController::class, 'adminIndex'])
    //         
    //         ->name('admin.pedidos.index');

    //     Route::get('stats', [AdminPedidosController::class, 'adminStats'])
    //         
    //         ->name('admin.pedidos.stats');

    //     Route::patch('{pedido}/force-status', [AdminPedidosController::class, 'forceStatus'])
    //         
    //         ->name('admin.pedidos.force-status');

    //     Route::delete('{pedido}', [AdminPedidosController::class, 'destroy'])
    //         
    //         ->name('admin.pedidos.destroy');

    //     Route::get('reports', [AdminPedidosController::class, 'adminReports'])
    //         
    //         ->name('admin.pedidos.reports');

    //     Route::get('{pedido}/audit', [AdminPedidosController::class, 'auditLog'])
    //         
    //         ->name('admin.pedidos.audit');
    // });

    /**
     * DASHBOARD ADMINISTRATIVO
     */

    Route::prefix('dashboard')->group(function () {
        // Endpoint unificado principal (15 días, presupuesto avg, SPP avg, totales, serie diaria)
        Route::get('dashboard-datos', [AdminHomeControler::class, 'dashboardDatos']);

        // Endpoints legados — compatibilidad
        Route::get('metricas-home', [AdminHomeControler::class, 'metricasHome']);
        Route::get('catalogos-resumen', [AdminHomeControler::class, 'getCatalogosCountItems']);
        Route::get('usuarios-activos', [AdminHomeControler::class, 'getUsuariosActivosEndpoint']);

        // SPP por proveedor
        Route::get('spp-proveedores', [AdminDashboardController::class, 'getMetricasSppPorProveedor']);

        // Métricas avanzadas (tendencias 30 días)
        Route::get('metricas-avanzadas', [AdminHomeControler::class, 'metricasAvanzadas']);
    });
    
    /**
     * HOMOLOGACIÓN DE PROVEEDORES DUPLICADOS
     * Sistema para reasignar usuarios entre proveedores con la misma razón social
     */
    // Route::prefix('homologacion')->group(function () {
    //     // Reporte de proveedores duplicados con metricas de SPP
    //     Route::get('reporte-proveedores-duplicados', [ProveedorHomologacionController::class, 'reporteProveedoresDuplicados'])
    //         
    //         ->name('admin.homologacion.proveedores.reporte-duplicados');

    //     // Listar proveedores para homologación
    //     Route::get('proveedores', [ProveedorHomologacionController::class, 'listarProveedores'])
    //         
    //         ->name('admin.homologacion.proveedores.index');

    //     // Obtener detalle de un proveedor específico
    //     Route::get('proveedores/{id}', [ProveedorHomologacionController::class, 'obtenerDetalleProveedor'])
    //         
    //         ->name('admin.homologacion.proveedores.show');

    //     // Obtener usuarios de múltiples proveedores para reasignar
    //     Route::post('usuarios-para-reasignar', [ProveedorHomologacionController::class, 'obtenerUsuariosParaReasignar'])
    //         
    //         ->name('admin.homologacion.usuarios-para-reasignar');

    //     // Previsualizar la homologación (sin ejecutar)
    //     Route::post('previsualizar', [ProveedorHomologacionController::class, 'previsualizarHomologacion'])
    //         
    //         ->name('admin.homologacion.previsualizar');

    //     // Ejecutar la homologación
    //     Route::post('ejecutar', [ProveedorHomologacionController::class, 'ejecutarHomologacion'])
    //         
    //         ->name('admin.homologacion.ejecutar');
    // });

    /**
     * INTEGRACIÓN CON SERVICIOS EXTERNOS
     */
    // Route::prefix('integracion')->group(function () {
    //     Route::post('pedidos/{pedido}/sync-billing', [PedidoController::class, 'syncBilling'])
    //         
    //         ->name('admin.integration.pedidos.sync-billing');

    //     Route::post('pedidos/{pedido}/generate-invoice', [PedidoController::class, 'generateInvoice'])
    //         
    //         ->name('admin.integration.pedidos.generate-invoice');

    //     Route::post('pedidos/{pedido}/payment-confirmed', [PedidoController::class, 'paymentConfirmed'])
    //         
    //         ->name('admin.integration.pedidos.payment-confirmed');
    // });
});

/**
 * RUTAS GLOBALES ADMINISTRATIVAS (COMPATIBILIDAD)
 * Mantienen el comportamiento existente
 */
Route::middleware(['auth:sanctum', 'role:' . UserRoleEnumerate::ADMINISTRADOR->value])->group(function () {

    // Resumen de catálogos (compatibilidad)
    Route::get('catalogos-resumen', [AdminHomeControler::class, 'getCatalogosCountItems']);

    // API Resources (compatibilidad)
    Route::apiResource('users', UserController::class);
    Route::apiResource('proveedores', AdminProveedorController::class)->except(['index']);
    Route::apiResource('sucursales', SucursalController::class)->except(['index']);
    Route::apiResource('productos', ProductoController::class)->except(['index']);
    Route::apiResource('imagenes', ProductoImagenController::class)->except(['index']);
    Route::apiResource('unidades-medida', UnidadMedidaController::class)->except(['index']);
    Route::apiResource('categorias', CategoriaController::class)->except(['index']);
    Route::apiResource('marcas', MarcaController::class)->except(['index']);
    Route::apiResource('tipos-empresa', TipoEmpresaController::class)->except(['index']);

    // Dashboard stats (compatibilidad)
    Route::get('dashboard/admin/stats-completas', [AdminDashboardController::class, 'getStatsCompletas']);
    Route::get('dashboard/admin/metricas-rendimiento', [AdminDashboardController::class, 'getMetricasRendimiento']);
});

// /**
//  * RUTAS ADMINISTRATIVAS DE PEDIDOS (COMPATIBILIDAD)
//  */
// Route::middleware(['auth:sanctum', 'role:ADMINISTRADOR'])->group(function () {

//     Route::prefix('admin/pedidos')->group(function () {

//         // Listar todos los pedidos (compatibilidad)
//         Route::get('/', [AdminPedidosController::class, 'adminIndex'])
//             ->name('admin.pedidos.index');

//         // Estadísticas generales (compatibilidad)
//         Route::get('stats', [AdminPedidosController::class, 'adminStats'])
//             ->name('admin.pedidos.stats');

//         // Forzar cambio de estatus (compatibilidad)
//         Route::patch('{pedido}/force-status', [AdminPedidosController::class, 'forceStatus'])
//             ->name('admin.pedidos.force-status');

//         // Eliminar pedido (compatibilidad)
//         Route::delete('{pedido}', [AdminPedidosController::class, 'destroy'])
//             ->name('admin.pedidos.destroy');

//         // Reportes avanzados (compatibilidad)
//         Route::get('reports', [AdminPedidosController::class, 'adminReports'])
//             ->name('admin.pedidos.reports');

//         // Auditoria de pedidos (compatibilidad)
//         Route::get('{pedido}/audit', [AdminPedidosController::class, 'auditLog'])
//             ->name('admin.pedidos.audit');
//     });
// });

// /**
//  * INTEGRACIÓN CON SERVICIOS EXTERNOS (COMPATIBILIDAD)
//  */
// Route::prefix('integration')->middleware(['auth:sanctum'])->group(function () {

//     // Sincronizar con sistema de facturación (compatibilidad)
//     Route::post('pedidos/{pedido}/sync-billing', [PedidoController::class, 'syncBilling'])
//         ->name('integration.pedidos.sync-billing');

//     // Generar factura automática (compatibilidad)
//     Route::post('pedidos/{pedido}/generate-invoice', [PedidoController::class, 'generateInvoice'])
//         ->name('integration.pedidos.generate-invoice');

//     // Webhook de confirmación de pago (compatibilidad)
//     Route::post('pedidos/{pedido}/payment-confirmed', [PedidoController::class, 'paymentConfirmed'])
//         ->name('integration.pedidos.payment-confirmed');
// });
