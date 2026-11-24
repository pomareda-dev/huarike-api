<?php

namespace App\Policies;

use App\Models\Reserva;
use App\Models\User;

/**
 * Una reserva solo la ve y la cancela su dueño; el admin pasa siempre
 * (docs §2 R3).
 */
class ReservaPolicy
{
    public function view(User $actor, Reserva $reserva): bool
    {
        return $actor->esAdmin() || $actor->correo === $reserva->correo;
    }

    public function cancel(User $actor, Reserva $reserva): bool
    {
        return $this->view($actor, $reserva);
    }
}
