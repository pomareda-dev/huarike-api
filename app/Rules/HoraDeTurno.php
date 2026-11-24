<?php

namespace App\Rules;

use App\Models\Turno;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La hora debe pertenecer a los slots de un turno **activo** de la tabla
 * `turnos`, nunca a una constante (docs §5.2, RH3).
 */
class HoraDeTurno implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Elige un horario de reserva disponible.');

            return;
        }

        $hora = substr($value, 0, 5);

        $existe = Turno::query()
            ->activos()
            ->get()
            ->contains(fn (Turno $turno) => in_array($hora, array_map(
                static fn (string $slot) => substr($slot, 0, 5),
                $turno->slots ?? [],
            ), true));

        if (! $existe) {
            $fail('Elige un horario de reserva disponible.');
        }
    }
}
