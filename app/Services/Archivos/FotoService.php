<?php

namespace App\Services\Archivos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Fotos de platos y promociones (docs §8.3): disco `public`, nombre hash y
 * ruta relativa guardada en la columna `foto`.
 */
class FotoService
{
    /**
     * Guarda la imagen y devuelve la ruta relativa ("platos/abc123.jpg").
     *
     * @param  string  $carpeta  "platos" o "promociones"
     */
    public function guardar(UploadedFile $foto, string $carpeta): string
    {
        return $foto->store($carpeta, 'public');
    }

    /**
     * Elimina la foto anterior, si existía.
     */
    public function eliminar(?string $ruta): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }

        Storage::disk('public')->delete($ruta);
    }

    /**
     * Ruta pública que consume el SPA ("storage/platos/abc.jpg").
     */
    public static function url(?string $ruta): ?string
    {
        return $ruta === null || $ruta === '' ? null : 'storage/'.ltrim($ruta, '/');
    }
}
