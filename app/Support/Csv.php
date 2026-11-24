<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Respuestas CSV del panel: cabecera en español, separador coma y BOM UTF-8
 * para que Excel abra los acentos (`csvDeReservas`/`csvDeUsuarios`).
 */
final class Csv
{
    /**
     * @param  list<string>  $encabezado
     * @param  iterable<int, list<string|int|float|null>>  $filas
     */
    public static function descargar(string $nombreArchivo, array $encabezado, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezado, $filas): void {
            $salida = fopen('php://output', 'wb');

            // BOM para que Excel respete la codificación.
            fwrite($salida, "\xEF\xBB\xBF");

            self::escribirFila($salida, $encabezado);

            foreach ($filas as $fila) {
                self::escribirFila($salida, $fila);
            }

            fclose($salida);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $salida
     * @param  list<string|int|float|null>  $fila
     */
    private static function escribirFila($salida, array $fila): void
    {
        fputcsv($salida, array_map(static fn ($valor) => (string) ($valor ?? ''), $fila), ',', '"', '');
    }
}
