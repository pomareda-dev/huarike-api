<?php

namespace App\Models;

use App\Enums\CategoriaPromocion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promocion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'promociones';

    protected $fillable = [
        'nombre',
        'slug',
        'destacada',
        'vigencia',
        'categoria',
        'condiciones',
        'codigo',
        'precio_texto',
        'foto',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'destacada' => 'boolean',
            'categoria' => CategoriaPromocion::class,
            'condiciones' => 'array',
            'activa' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Promocion>  $query
     */
    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }
}
