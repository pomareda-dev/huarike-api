<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = [
        'nombre',
        'posicion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Plato, $this>
     */
    public function platos(): HasMany
    {
        return $this->hasMany(Plato::class);
    }

    /**
     * @param  Builder<Categoria>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }

    /**
     * @param  Builder<Categoria>  $query
     */
    public function scopeOrdenadas(Builder $query): void
    {
        $query->orderBy('posicion')->orderBy('nombre');
    }
}
