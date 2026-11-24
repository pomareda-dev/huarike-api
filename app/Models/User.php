<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'correo',
        'password',
        'telefono',
        'rol',
        'activa',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'rol' => Rol::class,
            'activa' => 'boolean',
        ];
    }

    /**
     * Reservas vinculadas a la cuenta (las del panel sin cuenta no la tienen).
     *
     * @return HasMany<Reserva, $this>
     */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activa', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeAdministradores(Builder $query): void
    {
        $query->where('rol', Rol::Admin->value);
    }

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }

    /**
     * Cuentas que pueden iniciar sesión: activas y con correo verificado
     * por registro propio (el prototipo no maneja verificación, así que solo
     * importa `activa`).
     */
    public function puedeIngresar(): bool
    {
        return $this->activa === true;
    }
}
