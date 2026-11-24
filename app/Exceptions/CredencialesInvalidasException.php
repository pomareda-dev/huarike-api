<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Credenciales inválidas en el login (docs §4 A2): 401 con `message` y, cuando
 * aplica, `campo` para que el SPA marque el input correcto.
 */
class CredencialesInvalidasException extends Exception
{
    public function __construct(string $message, private readonly ?string $campo = null)
    {
        parent::__construct($message);
    }

    public function campo(): ?string
    {
        return $this->campo;
    }

    public function render(): JsonResponse
    {
        $cuerpo = ['message' => $this->getMessage()];

        if ($this->campo !== null) {
            $cuerpo['campo'] = $this->campo;
        }

        return response()->json($cuerpo, 401);
    }
}
