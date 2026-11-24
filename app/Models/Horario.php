<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Franja de atención del restaurante (`HORARIOS` en src/lib/horario.js).
 */
class Horario extends Model
{
    use HasFactory;

    protected $table = 'horarios';

    protected $fillable = [
        'nombre',
        'dias',
        'abre',
        'cierra',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'dias' => 'array',
            'activa' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Horario>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->where('activa', true);
    }
}
