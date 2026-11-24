<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Teléfono peruano de 9 dígitos, con o sin prefijo +51 y con espacios o
 * guiones opcionales (`validarTelefono` en src/lib/cuenta.js).
 */
class TelefonoPeruano implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitos = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digitos, '51') && strlen($digitos) === 11) {
            $digitos = substr($digitos, 2);
        }

        if (! preg_match('/^9\d{8}$/', $digitos)) {
            $fail('El teléfono debe tener 9 dígitos (puede incluir +51).');
        }
    }

    /**
     * Forma canónica para guardar: "987654321".
     */
    public static function normalizar(?string $valor): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor) ?? '';

        if (str_starts_with($digitos, '51') && strlen($digitos) === 11) {
            $digitos = substr($digitos, 2);
        }

        return $digitos;
    }
}
