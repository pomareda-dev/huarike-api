<?php

namespace App\Services\Panel;

use App\Models\Promocion;
use App\Services\Archivos\FotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Promociones gestionadas desde el panel (docs §5.8): slug único derivado del
 * nombre y foto opcional en el disco público.
 */
class PromocionService
{
    public function __construct(private readonly FotoService $fotos) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos): Promocion
    {
        return Promocion::create([
            'nombre' => $datos['nombre'],
            'slug' => $this->slugUnico($datos['slug'] ?? null, $datos['nombre']),
            'destacada' => (bool) ($datos['destacada'] ?? false),
            'vigencia' => $datos['vigencia'],
            'categoria' => $datos['categoria'],
            'condiciones' => $datos['condiciones'] ?? [],
            'codigo' => $datos['codigo'],
            'precio_texto' => $datos['precio_texto'],
            'activa' => (bool) ($datos['activa'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Promocion $promocion, array $datos): Promocion
    {
        $promocion->fill([
            'nombre' => $datos['nombre'],
            'slug' => $this->slugUnico($datos['slug'] ?? null, $datos['nombre'], $promocion->id),
            'destacada' => array_key_exists('destacada', $datos) ? (bool) $datos['destacada'] : $promocion->destacada,
            'vigencia' => $datos['vigencia'],
            'categoria' => $datos['categoria'],
            'condiciones' => $datos['condiciones'] ?? $promocion->condiciones ?? [],
            'codigo' => $datos['codigo'],
            'precio_texto' => $datos['precio_texto'],
            'activa' => array_key_exists('activa', $datos) ? (bool) $datos['activa'] : $promocion->activa,
        ])->save();

        return $promocion;
    }

    public function subirFoto(Promocion $promocion, UploadedFile $foto): Promocion
    {
        $anterior = $promocion->foto;
        $ruta = $this->fotos->guardar($foto, 'promociones');

        $promocion->forceFill(['foto' => $ruta])->save();
        $this->fotos->eliminar($anterior);

        return $promocion;
    }

    public function eliminar(Promocion $promocion): void
    {
        $promocion->delete();
    }

    /**
     * Slug estable y único; si el nombre ya existe se agrega un sufijo.
     */
    private function slugUnico(?string $slug, string $nombre, ?int $ignorarId = null): string
    {
        $base = Str::slug($slug !== null && $slug !== '' ? $slug : $nombre);

        if ($base === '') {
            $base = 'promocion';
        }

        $candidato = $base;
        $sufijo = 2;

        while (Promocion::withTrashed()
            ->where('slug', $candidato)
            ->when($ignorarId !== null, fn ($query) => $query->whereKeyNot($ignorarId))
            ->exists()) {
            $candidato = $base.'-'.$sufijo;
            $sufijo++;
        }

        return $candidato;
    }
}
