<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservaPlato extends Model
{
    protected $table = 'reserva_platos';

    protected $fillable = [
        'reserva_id',
        'plato_id',
        'nombre',
        'precio',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'cantidad' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Reserva, $this>
     */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /**
     * @return BelongsTo<Plato, $this>
     */
    public function plato(): BelongsTo
    {
        return $this->belongsTo(Plato::class);
    }
}
