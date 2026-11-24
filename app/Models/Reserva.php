<?php

namespace App\Models;

use App\Enums\EstadoReserva;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'folio',
        'user_id',
        'nombre',
        'correo',
        'telefono',
        'notas',
        'fecha',
        'hora',
        'personas',
        'mesa_id',
        'estado',
        'subtotal',
        'creada_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'personas' => 'integer',
            'estado' => EstadoReserva::class,
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * La columna es TIME: el contrato del SPA pide `HH:MM` textual.
     *
     * @return Attribute<string, string>
     */
    protected function hora(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor) => $valor === null ? null : substr($valor, 0, 5),
            set: fn (?string $valor) => $valor === null ? null : substr($valor, 0, 5),
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Mesa, $this>
     */
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    /**
     * @return HasMany<ReservaPlato, $this>
     */
    public function platos(): HasMany
    {
        return $this->hasMany(ReservaPlato::class);
    }

    /**
     * @return HasMany<ReservaHistorial, $this>
     */
    public function historial(): HasMany
    {
        return $this->hasMany(ReservaHistorial::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @param  Builder<Reserva>  $query
     */
    public function scopeSinChoque(Builder $query): void
    {
        $query->whereNotIn('estado', array_map(
            fn (EstadoReserva $estado) => $estado->value,
            EstadoReserva::sinChoque(),
        ));
    }

    /**
     * @param  Builder<Reserva>  $query
     */
    public function scopeDelCliente(Builder $query, string $correo): void
    {
        $query->where('correo', $correo);
    }
}
