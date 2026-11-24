<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Turno de reserva con sus slots (`TURNOS_RESERVA` en src/lib/reservas.js).
 * La PK es el identificador textual ("almuerzo", "cena") porque es la que
 * viaja en las URLs y en las respuestas del SPA.
 */
class Turno extends Model
{
    use HasFactory;

    protected $table = 'turnos';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre',
        'slots',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'activa' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Turno>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activa', true);
    }
}
