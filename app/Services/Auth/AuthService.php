<?php

namespace App\Services\Auth;

use App\Enums\Rol;
use App\Exceptions\CredencialesInvalidasException;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Registro, login y recuperación de contraseña (docs §4).
 */
class AuthService
{
    /**
     * Registro público: la cuenta siempre nace como cliente activa.
     *
     * @param  array{nombre: string, correo: string, telefono: string, password: string}  $datos
     * @return array{user: User, token: string}
     */
    public function registrar(array $datos): array
    {
        $user = User::create([
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'telefono' => $datos['telefono'],
            'password' => $datos['password'],
            'rol' => Rol::Cliente,
            'activa' => true,
        ]);

        return ['user' => $user, 'token' => $this->emitirToken($user)];
    }

    /**
     * Login con las tres causas de rechazo por separado (correo inexistente,
     * clave incorrecta, cuenta desactivada) para que el SPA marque el campo.
     *
     * @return array{user: User, token: string}
     */
    public function iniciarSesion(string $correo, string $password): array
    {
        $user = User::query()->where('correo', $correo)->first();

        if ($user === null) {
            throw new CredencialesInvalidasException(
                'No encontramos una cuenta con ese correo.',
                'correo',
            );
        }

        if (! Hash::check($password, $user->password)) {
            throw new CredencialesInvalidasException('La contraseña no coincide.', 'password');
        }

        if (! $user->activa) {
            throw new CredencialesInvalidasException(
                'Tu cuenta está desactivada. Escríbenos para reactivarla.',
                'correo',
            );
        }

        return ['user' => $user, 'token' => $this->emitirToken($user)];
    }

    public function emitirToken(User $user): string
    {
        return $user->createToken('spa')->plainTextToken;
    }

    public function cerrarSesion(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Envía el enlace si la cuenta existe. La respuesta al cliente es la misma
     * en ambos casos para no enumerar cuentas (docs §4 A5).
     */
    public function enviarEnlace(string $correo): void
    {
        $user = User::query()->where('correo', $correo)->first();

        if ($user === null || ! $user->activa) {
            return;
        }

        $token = Password::broker()->createToken($user);

        $user->notify(new ResetPasswordNotification($token));
    }

    /**
     * Restablece la contraseña y revoca todos los tokens activos.
     *
     * @param  array{correo: string, password: string}  $datos
     */
    public function restablecer(array $datos, string $token): string
    {
        $estado = Password::broker()->reset(
            [
                'correo' => $datos['correo'],
                'password' => $datos['password'],
                'password_confirmation' => $datos['password'],
                'token' => $token,
            ],
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill(['password' => $password])->save();
                    $user->tokens()->delete();
                });
            },
        );

        return $estado;
    }
}
