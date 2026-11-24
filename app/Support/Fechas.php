<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Utilidades de fecha/hora del negocio. El restaurante opera siempre en la
 * zona configurada (`America/Lima`) y el SPA intercambia `YYYY-MM-DD` y `HH:MM`.
 *
 * Los nombres en español se resuelven con tablas propias: la extensión `intl`
 * no está garantizada en el entorno de despliegue.
 */
final class Fechas
{
    /** @var list<string> domingo = 0, igual que el prototipo */
    private const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    /** @var list<string> */
    private const MESES = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    /** @var list<string> */
    private const MESES_CORTOS = [
        'ene', 'feb', 'mar', 'abr', 'may', 'jun',
        'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
    ];

    public static function zona(): string
    {
        return (string) config('huarike.zona_horaria', 'America/Lima');
    }

    public static function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zona());
    }

    public static function hoy(): CarbonImmutable
    {
        return self::ahora()->startOfDay();
    }

    /**
     * Convierte "HH:MM" en minutos desde medianoche.
     */
    public static function minutos(?string $hora): int
    {
        if (! is_string($hora) || ! preg_match('/^(\d{1,2}):(\d{2})/', $hora, $partes)) {
            return 0;
        }

        return ((int) $partes[1]) * 60 + (int) $partes[2];
    }

    /**
     * Instante exacto de una fecha+hora del negocio.
     */
    public static function instante(string $fecha, string $hora): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            substr($fecha, 0, 10).' '.substr($hora, 0, 5),
            self::zona(),
        );
    }

    public static function carbon(string $fecha): CarbonImmutable
    {
        return CarbonImmutable::parse(substr($fecha, 0, 10), self::zona());
    }

    /**
     * Fecha larga de la confirmación: "jueves, 20 de noviembre" (`fechaLarga`).
     */
    public static function larga(string $fecha): string
    {
        $carbon = self::carbon($fecha);

        return sprintf(
            '%s, %d de %s',
            self::DIAS[$carbon->dayOfWeek],
            $carbon->day,
            self::MESES[$carbon->month - 1],
        );
    }

    /**
     * Fecha corta de tablas: "20 nov" (`fechaCorta`).
     */
    public static function corta(string $fecha): string
    {
        $carbon = self::carbon($fecha);

        return sprintf('%d %s', $carbon->day, self::MESES_CORTOS[$carbon->month - 1]);
    }

    /**
     * Fecha numérica del CSV de usuarios: "23/11/2026".
     */
    public static function numerica(string $fecha): string
    {
        $carbon = self::carbon($fecha);

        return $carbon->format('d/m/Y');
    }

    /**
     * Día de la semana con domingo = 0.
     */
    public static function diaSemana(string $fecha): int
    {
        return (int) self::carbon($fecha)->dayOfWeek;
    }

    /**
     * Nombre del día de la semana ("lunes").
     */
    public static function nombreDia(int $dia): string
    {
        return self::DIAS[(($dia % 7) + 7) % 7];
    }

    /**
     * "8:00" en lugar de "08:00" para los textos del estado del local
     * (`sinCeroInicial` en src/lib/horario.js).
     */
    public static function horaSinCero(?string $hora): string
    {
        return preg_replace('/^0/', '', substr((string) $hora, 0, 5)) ?? '';
    }
}
