<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Violación de una regla de negocio: se responde 409 con `message`, el mismo
 * contrato que espera el SPA para conflictos de mesa, ventana de cancelación y
 * reglas R1/R2 (docs §6).
 */
class ReglaNegocioException extends Exception
{
    /**
     * @param  array<string, mixed>  $extra  Datos adicionales (p.ej. mesas válidas).
     */
    public function __construct(
        string $message,
        private readonly array $extra = [],
        int $status = 409,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }

    public function status(): int
    {
        return $this->getCode() ?: 409;
    }

    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => $this->getMessage()] + $this->extra,
            $this->status(),
        );
    }
}
