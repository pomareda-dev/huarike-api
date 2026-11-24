<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Autentica la siguiente request con un token nuevo del usuario indicado.
     * Los tests comparten contenedor: hay que olvidar los guards ya resueltos
     * para que el cambio de token tenga efecto real.
     */
    protected function actuarComo(User $usuario): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withToken($usuario->createToken('spa')->plainTextToken);
    }
}
