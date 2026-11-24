<?php

namespace App\Enums;

/**
 * Clasificación de un slot de reserva según las mesas libres restantes
 * (`estadoSlot` en src/lib/reservas.js).
 */
enum EstadoSlot: string
{
    case Disponible = 'disponible';
    case AltaDemanda = 'alta-demanda';
    case Ocupado = 'ocupado';

    /**
     * Deriva el estado a partir de la cantidad de mesas libres.
     */
    public static function desdeMesasLibres(int $mesasLibres): self
    {
        $disponibleDesde = (int) config('huarike.disponibilidad.disponible_desde', 3);
        $altaDemandaDesde = (int) config('huarike.disponibilidad.alta_demanda_desde', 1);

        return match (true) {
            $mesasLibres >= $disponibleDesde => self::Disponible,
            $mesasLibres >= $altaDemandaDesde => self::AltaDemanda,
            default => self::Ocupado,
        };
    }

    /**
     * Texto que consume la tarjeta de disponibilidad del SPA.
     */
    public function resumen(int $mesasLibres): string
    {
        return match ($this) {
            self::Disponible, self::AltaDemanda => $mesasLibres === 1
                ? 'Queda 1 mesa'
                : "Quedan {$mesasLibres} mesas",
            self::Ocupado => 'Sin mesas para este grupo',
        };
    }
}
