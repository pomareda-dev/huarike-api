<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartaController;
use App\Http\Controllers\Api\V1\DisponibilidadController;
use App\Http\Controllers\Api\V1\Panel\CartaController as PanelCartaController;
use App\Http\Controllers\Api\V1\Panel\CategoriaController;
use App\Http\Controllers\Api\V1\Panel\MesaController;
use App\Http\Controllers\Api\V1\Panel\PlatoController;
use App\Http\Controllers\Api\V1\Panel\UsuarioController;
use App\Http\Controllers\Api\V1\ReservaController;
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

    // Sitio público.
    Route::middleware('throttle:publico')->group(function (): void {
        Route::get('carta', [CartaController::class, 'carta']);
        Route::get('horarios', [CartaController::class, 'horarios']);
        Route::get('turnos', [CartaController::class, 'turnos']);
        Route::get('mesas', [DisponibilidadController::class, 'mesasActivas']);
        Route::get('disponibilidad/mesas', [DisponibilidadController::class, 'mesas']);
        Route::get('disponibilidad', [DisponibilidadController::class, 'index']);
    });

    // Reservas del cliente autenticado.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('reservas', [ReservaController::class, 'store']);
        Route::get('mis-reservas', [ReservaController::class, 'index']);
        Route::get('reservas/{folio}', [ReservaController::class, 'show']);
        Route::patch('reservas/{folio}/cancelar', [ReservaController::class, 'cancelar']);
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

            Route::get('carta', [PanelCartaController::class, 'index']);

            Route::post('categorias', [CategoriaController::class, 'store']);
            Route::match(['put', 'patch'], 'categorias/{categoria}', [CategoriaController::class, 'update']);
            Route::delete('categorias/{categoria}', [CategoriaController::class, 'destroy']);

            Route::post('platos', [PlatoController::class, 'store']);
            Route::match(['put', 'patch'], 'platos/{plato}', [PlatoController::class, 'update']);
            Route::delete('platos/{plato}', [PlatoController::class, 'destroy']);

            Route::get('mesas', [MesaController::class, 'index']);
            Route::post('mesas', [MesaController::class, 'store']);
            Route::match(['put', 'patch'], 'mesas/{mesa}', [MesaController::class, 'update']);
            Route::patch('mesas/{mesa}/activar', [MesaController::class, 'activar']);
            Route::patch('mesas/{mesa}/desactivar', [MesaController::class, 'desactivar']);
            Route::delete('mesas/{mesa}', [MesaController::class, 'destroy']);
        });
});
