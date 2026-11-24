<?php

namespace App\Models;

use App\Enums\Zona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mesa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mesas';

    protected $fillable = [
        'numero',
        'zona',
        'capacidad',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'zona' => Zona::class,
            'capacidad' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Reserva, $this>
     */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    /**
     * @param  Builder<Mesa>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }

    /**
     * Etiqueta usada en el CSV del panel ("Mesa 5" / "Sin mesa").
     */
    public function etiquetaCsv(): string
    {
        return $this->numero ? "Mesa {$this->numero}" : 'Sin mesa';
    }
}
