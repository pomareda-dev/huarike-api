<?php

namespace App\Services\Panel;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\User;

/**
 * Gestión de cuentas desde el panel con las reglas de integridad R1 y R2
 * (docs §2): nadie se autodesactiva y nunca queda el sistema sin
 * administradores activos.
 */
class UsuarioService
{
    /**
     * @param  array{nombre: string, correo: string, telefono: string, rol: Rol|string, password: string}  $datos
     */
    public function crear(array $datos): User
    {
        return User::create([
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'telefono' => $datos['telefono'],
            'password' => $datos['password'],
            'rol' => $datos['rol'] instanceof Rol ? $datos['rol'] : Rol::from($datos['rol']),
            'activa' => true,
        ]);
    }

    /**
     * @param  array{nombre: string, correo: string, telefono: string, rol: Rol|string}  $datos
     */
    public function actualizar(User $usuario, array $datos, User $actor): User
    {
        $rol = $datos['rol'] instanceof Rol ? $datos['rol'] : Rol::from($datos['rol']);

        if ($rol !== $usuario->rol) {
            $this->verificarUltimoAdmin($usuario, $rol, $actor);
        }

        $usuario->fill([
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'telefono' => $datos['telefono'],
            'rol' => $rol,
        ])->save();

        return $usuario;
    }

    public function desactivar(User $usuario, User $actor): User
    {
        // R1: un admin no puede desactivar su propia cuenta.
        if ($usuario->is($actor)) {
            throw new ReglaNegocioException(
                'No puedes desactivar tu propia cuenta.',
                ['motivo' => 'autodesactivacion'],
            );
        }

        // R2: desactivar al último admin activo dejaría el panel sin acceso.
        $this->verificarUltimoAdmin($usuario, Rol::Cliente, $actor, desactivar: true);

        $usuario->forceFill(['activa' => false])->save();
        $usuario->tokens()->delete();

        return $usuario;
    }

    public function activar(User $usuario): User
    {
        $usuario->forceFill(['activa' => true])->save();

        return $usuario;
    }

    public function cambiarRol(User $usuario, Rol $rol, User $actor): User
    {
        if ($rol !== $usuario->rol) {
            $this->verificarUltimoAdmin($usuario, $rol, $actor);
        }

        $usuario->forceFill(['rol' => $rol])->save();

        return $usuario;
    }

    /**
     * R2: rechaza la operación cuando dejaría cero administradores activos.
     * Se evalúa sobre el estado *después* del cambio.
     */
    private function verificarUltimoAdmin(User $usuario, Rol $rolFinal, User $actor, bool $desactivar = false): void
    {
        $eraAdminActivo = $usuario->rol === Rol::Admin && $usuario->activa;

        if (! $eraAdminActivo) {
            return;
        }

        $dejaDeSerAdminActivo = $rolFinal !== Rol::Admin || $desactivar;

        if (! $dejaDeSerAdminActivo) {
            return;
        }

        $otrosAdminsActivos = User::query()
            ->administradores()
            ->activos()
            ->whereKeyNot($usuario->getKey())
            ->count();

        if ($otrosAdminsActivos === 0) {
            throw new ReglaNegocioException(
                'Debe quedar al menos un administrador activo en el sistema.',
                ['motivo' => 'ultimo-admin'],
            );
        }
    }
}
