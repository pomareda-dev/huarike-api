<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Panel\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de El Huarike
|--------------------------------------------------------------------------
|
| Todas las rutas viven bajo /api/v1 (el prefijo /api lo agrega el framework).
| La autenticación es por token Bearer de Sanctum: no hay sesión web.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('password/email', [AuthController::class, 'passwordEmail'])->middleware('throttle:auth');
        Route::post('password/reset', [AuthController::class, 'passwordReset']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Panel: exclusivo de administradores activos.
    Route::middleware(['auth:sanctum', 'can:viewAny,App\Models\User'])
        ->prefix('panel')
        ->group(function (): void {
            Route::get('usuarios/resumen', [UsuarioController::class, 'resumen']);
            Route::get('usuarios/export.csv', [UsuarioController::class, 'exportCsv']);
            Route::get('usuarios', [UsuarioController::class, 'index']);
            Route::post('usuarios', [UsuarioController::class, 'store']);
            Route::match(['put', 'patch'], 'usuarios/{usuario}', [UsuarioController::class, 'update']);
            Route::patch('usuarios/{usuario}/activar', [UsuarioController::class, 'activar']);
            Route::patch('usuarios/{usuario}/desactivar', [UsuarioController::class, 'desactivar']);
            Route::patch('usuarios/{usuario}/rol', [UsuarioController::class, 'rol']);
        });
});
