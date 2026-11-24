<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora acumulativa de una reserva: cada cambio agrega una fila, nunca
 * sobreescribe (`historial[]` en `construirReserva`).
 */
class ReservaHistorial extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'reserva_historial';

    protected $fillable = [
        'reserva_id',
        'tipo',
        'texto',
        'autor',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Reserva, $this>
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
