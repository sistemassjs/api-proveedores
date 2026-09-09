<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Notifications\OrdenCompraNotificationController;
use App\Http\Controllers\Notifications\SolicitudPagoNotificationController;

// Rutas de autenticación de broadcasting
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// =============================================================================
// NOTIFICACIONES - TODAS LAS RUTAS BAJO UN SOLO PREFIJO
// =============================================================================
Route::prefix('notifications')->group(function () {

    // -------------------------------------------------------------------------
    // WEBHOOKS DESDE API CONSTRUCCIONES (protegidas con ApiKey)
    // -------------------------------------------------------------------------
    Route::middleware(['apikey:api_construcciones'])->group(function () {
        // Orden de Compra
        Route::post('/nueva-orden', [OrdenCompraNotificationController::class, 'nuevaOrden']);
        
        // Solicitud de Pago
        Route::post('/solicitud-pago/pagada', [SolicitudPagoNotificationController::class, 'solicitudPagada']);
        Route::post('/solicitud-pago/rechazada', [SolicitudPagoNotificationController::class, 'solicitudRechazada']);
    });

    // -------------------------------------------------------------------------
    // GESTIÓN DE NOTIFICACIONES DEL USUARIO (protegidas con Sanctum)
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [NotificationController::class, 'getNotifications']);
        Route::get('/poll', [NotificationController::class, 'poll']); // Sin audit para no llenar logs
        Route::post('/test', [NotificationController::class, 'sendTest']);
        Route::post('/send', [NotificationController::class, 'sendToCurrentUser']);
        Route::post('/send/{userId}', [NotificationController::class, 'sendToUser']);
        Route::patch('/{notificationId}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{notificationId}', [NotificationController::class, 'destroy']);
        Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/marcar-leida/por-tipo-notificacion', [NotificationController::class, 'markAsReadByTipoAndSP']);
    });
});
