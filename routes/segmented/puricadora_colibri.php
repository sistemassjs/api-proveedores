<?php



use App\Http\Controllers\PurificadoraPedidoController;

use Illuminate\Support\Facades\Route;



/*

|--------------------------------------------------------------------------

| Purificadora Colibrí — pedidos de agua

|--------------------------------------------------------------------------

| store: público

| resto: auth:sanctum

*/



Route::prefix('purificadora-pedidos')
    ->name('purificadora-pedidos.')
    ->group(function () {
        Route::post('/', [PurificadoraPedidoController::class, 'store'])->name('store');
    });



Route::prefix('purificadora-pedidos')
    ->middleware(['auth:sanctum'])
    ->name('purificadora-pedidos.')
    ->group(function () {

        Route::get('/', [PurificadoraPedidoController::class, 'index'])->name('index');
        Route::put('{id}/actualizar', [PurificadoraPedidoController::class, 'actualizar'])->name('update');
        Route::put('{id}/eliminado', [PurificadoraPedidoController::class, 'marcarDelete'])->name('destroy');
        Route::get('{id}/marcar-pedido-proceso-whatsapp-enlace', [PurificadoraPedidoController::class, 'marcarPedidoProcesoWhatsappEnlace',])->name('marcar-pedido-proceso-whatsapp-enlace');
        Route::put('{id}/completado', [PurificadoraPedidoController::class, 'marcarCompletado'])->name('completado');
        Route::put('{id}/cancelado', [PurificadoraPedidoController::class, 'marcarCancelado'])->name('cancelado');
    });
