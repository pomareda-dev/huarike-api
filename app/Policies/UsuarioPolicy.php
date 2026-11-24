<?php

namespace App\Policies;

use App\Models\User;

/**
 * Todo el panel es exclusivo de administradores (docs §2).
 */
class UsuarioPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function view(User $actor, User $usuario): bool
    {
        return $actor->esAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function update(User $actor, User $usuario): bool
    {
        return $actor->esAdmin();
    }

    public function delete(User $actor, User $usuario): bool
    {
        return $actor->esAdmin();
    }
}
