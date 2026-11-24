<?php

namespace App\Providers;

use App\Models\Reserva;
use App\Models\User;
use App\Policies\ReservaPolicy;
use App\Policies\UsuarioPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, UsuarioPolicy::class);
        Gate::policy(Reserva::class, ReservaPolicy::class);

        // Login, registro y recuperación: 5 intentos por minuto y por IP
        // (docs §4 A4).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Endpoints públicos del sitio: 60 por minuto (docs §11).
        RateLimiter::for('publico', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Panel autenticado: 120 por minuto, suficiente para el polling de 30 s
        // y las pantallas con varios listados.
        RateLimiter::for('panel', fn (Request $request) => Limit::perMinute(120)->by(
            $request->user()?->id ?: $request->ip(),
        ));

        // En producción, bloquea migrate:fresh, db:wipe y similares.
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
