<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Hora estricta en formato HH:MM de 24 horas. `date_format:H:i` acepta
 * "25:00" porque PHP normaliza el desborde, así que la validación es propia.
 */
class HoraValida implements ValidationRule
{
    public function __construct(private readonly string $mensaje = 'Usa el formato HH:MM (00:00 a 23:59).') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            $fail($this->mensaje);
        }
    }
}
