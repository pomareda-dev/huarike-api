<?php

namespace App\Rules;

use App\Support\Fechas;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Una reserva solo puede ser para hoy o después (`esFechaValida`).
 */
class FechaDeReserva implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $fail('Elige una fecha válida.');

            return;
        }

        if ($value < Fechas::hoy()->toDateString()) {
            $fail('Elige una fecha de hoy en adelante.');
        }
    }
}
