<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\SocialAuthController;

/*
|--------------------------------------------------------------------------
| RUTAS DE AUTENTICACIÓN PROTEGIDAS
|--------------------------------------------------------------------------
| Estas rutas requieren autenticación con Sanctum
*/

Route::prefix('auth')->group(function () {


    /**
     * AUTENTICACION Y REGISTROG
     */
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('completar-registro', [AuthController::class, 'register_completar']);
    Route::post('register_proveedor', [AuthController::class, 'register_proveedor']);
    Route::post('register_proveedor/reenviar-correo', [AuthController::class, 'reenviarCorreoRegistroProveedor']);
    Route::post('register_proveedor_completar', [AuthController::class, 'register_proveedor_completar']);
    Route::post('register_basico', [AuthController::class, 'register_proveedor_basico_sp']);
    Route::post('register_basico_completar', [AuthController::class, 'register_proveedor_basico_completar']);
    Route::post('completar-registro-proveedor', [AuthController::class, 'completarRegistroProveedor']);
    Route::post('asociar-proveedor-existente', [AuthController::class, 'asociar_proveedor_existente']);
    Route::post('verificar-email', [AuthController::class, 'verificarEmailExistente']);
    Route::get('verificar-email-token', [AuthController::class, 'verifyUpdatedEmail']);
    Route::post('verificar-razon-social', [AuthController::class, 'verificarRazonSocialExistente']);
    Route::post('verificar-telefono', [AuthController::class, 'verificarTelefonoExistente']);

    /**
     * RECUPERACIÓN DE CONTRASEÑA
     */
    Route::post('password/forgot', [AuthController::class, 'requestPasswordReset']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);

    /**
     * LOGIN SOCIAL (Socialite) — PWA redirect
     * Whitelist de providers en config('services.oauth.providers')
     */
    Route::middleware('throttle:20,1')->group(function () {
        Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->where('provider', '[a-z]+')
            ->name('auth.social.redirect');
        Route::get('{provider}/callback', [SocialAuthController::class, 'callback'])
            ->where('provider', '[a-z]+')
            ->name('auth.social.callback');
    });

    /**
     * PERFIL Y GESTIÓN DE CUENTA
     */
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('update-img-perfil', [AuthController::class, 'update_foto_perfil']);
        Route::post('update-credentials', [AuthController::class, 'updateUser']);
        Route::post('update-user-data', [AuthController::class, 'updateUserData']);
        Route::post('change-password', [AuthController::class, 'updatePassword']);
        Route::get('logout', [AuthController::class, 'logout']);
        Route::post('verificar-telefono-excluyendo-usuario', [AuthController::class, 'verificarTelefonoExistenteExcluyendoUsuario']);
        Route::post('verificar-email-excluyendo-usuario', [AuthController::class, 'verificarEmailExistenteExcluyendoUsuario']);

        /**
         * NOTIFICACIONES DE SP
         */
        Route::get('user/{id}/notifications/counts-sp-by-status', [\App\Http\Controllers\Notifications\NotificationController::class, 'countsSPByStatus']);
    });
});
