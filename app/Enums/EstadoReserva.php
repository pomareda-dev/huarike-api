<?php

namespace App\Enums;

/**
 * Estados del ciclo de vida de una reserva.
 *
 * Los valores son idénticos a los del frontend (`ESTADOS_RESERVA` en
 * src/lib/reservas.js) porque las etiquetas y colores del SPA se resuelven
 * por ese string exacto.
 */
enum EstadoReserva: string
{
    case Confirmada = 'confirmada';
    case PorConfirmar = 'por-confirmar';
    case ListaEspera = 'lista-espera';
    case NoShow = 'no-show';
    case Finalizada = 'finalizada';
    case Cancelada = 'cancelada';

    /**
     * Estados que agrupa el filtro `estado=pendiente` del panel.
     *
     * @return list<self>
     */
    public static function pendientes(): array
    {
        return [self::PorConfirmar, self::ListaEspera];
    }

    /**
     * Estados que retienen una mesa física. `lista-espera` no ocupa mesa y
     * `cancelada` libera la franja; esos dos son los que excluye la regla de
     * choque de 90 minutos.
     *
     * @return list<self>
     */
    public static function ocupanMesa(): array
    {
        return [self::Confirmada, self::PorConfirmar, self::NoShow, self::Finalizada];
    }

    /**
     * Estados excluidos del cálculo de choque de franja.
     *
     * @return list<self>
     */
    public static function sinChoque(): array
    {
        return [self::Cancelada, self::ListaEspera];
    }

    /**
     * Estados que cuentan como no-show en los reportes
     * (`cancelada + no-show`, según `metricasDelDia`).
     *
     * @return list<self>
     */
    public static function noShow(): array
    {
        return [self::Cancelada, self::NoShow];
    }

    /**
     * Estados que suman personas y reservas en los contadores del panel.
     *
     * @return list<self>
     */
    public static function contables(): array
    {
        return [self::Confirmada, self::PorConfirmar, self::NoShow, self::Finalizada];
    }

    /**
     * Etiqueta en español usada en CSV y respuestas del panel.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Confirmada => 'Confirmada',
            self::PorConfirmar => 'Por confirmar',
            self::ListaEspera => 'Lista de espera',
            self::NoShow => 'No show',
            self::Finalizada => 'Finalizada',
            self::Cancelada => 'Cancelada',
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $estado) => $estado->value, self::cases());
    }
}
