<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TipoEmpresaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoImagenController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProveedorPublicController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\MetricasLookerstudioController;
use App\Http\Controllers\ApiStatusController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS
|--------------------------------------------------------------------------
| Estas rutas no requieren autenticación
*/

Route::get('status', ApiStatusController::class);

/**
 * CATÁLOGOS PÚBLICOS
 */
Route::get('roles-index', [RoleController::class, 'index']);
Route::get('tipos-empresa-index', [TipoEmpresaController::class, 'index']);

// // Catálogos generales
Route::get('proveedores', [ProveedorController::class, 'index']);
Route::get('sucursales', [SucursalController::class, 'index']);
Route::get('productos', [ProductoController::class, 'index']);
Route::get('imagenes', [ProductoImagenController::class, 'index']);
Route::get('unidades-medida', [UnidadMedidaController::class, 'index']);
Route::get('categorias', [CategoriaController::class, 'index']);
Route::get('marcas', [MarcaController::class, 'index']);
Route::get('tipos-empresa', [TipoEmpresaController::class, 'index']);

/**
 * CONSULTAS PÚBLICAS ESPECIALIZADAS
 */
Route::middleware(['throttle:60,1'])->group(function () {
    // Webhook para actualizaciones de tracking (transportistas)
    Route::post('pedidos/{pedido}/tracking-update', [PedidoController::class, 'trackingUpdate'])
        ->name('pedidos.tracking-update');

    // Consulta pública de estado de pedido (con token)
    Route::get('pedidos/{pedido}/status/{token}', [PedidoController::class, 'publicStatus'])
        ->name('pedidos.public-status');
});



Route::get(
    'public/proveedor/{id}/compartir-constancia',
    [ProveedorPublicController::class, 'compartirConstancia']
);

/**
 * PERFIL PÚBLICO DE EMPRESA (enlace compartido sin autenticación)
 */
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get(
        'public/perfil/{token}',
        [ProveedorPublicController::class, 'perfilPublico']
    );
});

/**
 * PRESUPUESTOS PÚBLICOS (enlace compartido sin autenticación)
 */
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('public/presupuestos/{token}', [\App\Http\Controllers\PresupuestoPublicController::class, 'show']);
    Route::get('public/presupuestos/{token}/pdf', [\App\Http\Controllers\PresupuestoPublicController::class, 'descargarPdf']);
    Route::post('public/presupuestos/{token}/aceptar', [\App\Http\Controllers\PresupuestoPublicController::class, 'aceptar']);
    Route::post('public/presupuestos/{token}/rechazar', [\App\Http\Controllers\PresupuestoPublicController::class, 'rechazar']);
});

/**
 * REPORTES - DESCARGA PÚBLICA DE FACTURAS
 */
Route::get(
    'construcc/reportes/descargar-facturas-multiple/{spp_ids}/{tipo?}',
    [\App\Http\Controllers\ConstruccReportesController::class, 'descargarFacturasMultiple']
)->name('construcc.reportes.descargar-facturas-multiple')
    ->middleware(['throttle:60,1']);


/**
 * REPORTES - DESCARGA PÚBLICA DE COMPROBANTES DE PAGO
 */
Route::get(
    'construcc/reportes/descargar-comprobantes-pago/{pago_id}',
    [\App\Http\Controllers\ConstruccReportesController::class, 'descargarComprobantesPago']
)->name('construcc.reportes.descargar-comprobantes-pago')
    ->middleware(['throttle:60,1']);

/**
 * FORMULARIO DE CONTACTO
 */
Route::post('contacto/enviar', [ContactoController::class, 'enviarContacto'])
    ->name('contacto.enviar')
    ->middleware(['throttle:5,1']); // Máximo 5 envíos por minuto por IP


/**
 * Metricas públicas para dashboard (sin autenticación)
 */
Route::get('metricas/lookerstudio', [MetricasLookerstudioController::class, 'metricasLookerstudio'])
    ->name('metricas.lookerstudio');


// Route::get('test', function () {

//     $numero = '5216688564515';
//     $correoSms = $numero . '@itelcel.com';

//     Mail::raw('Tu código es 1234', function ($message) use ($correoSms) {
//         $message->to($correoSms)
//             ->subject('SMS');
//     });
// });
