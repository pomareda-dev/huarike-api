<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RecuperarClaveRequest;
use App\Http\Requests\Auth\RegistroRequest;
use App\Http\Requests\Auth\RestablecerClaveRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Autenticación por tokens Sanctum (docs §4).
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegistroRequest $request): JsonResponse
    {
        $resultado = $this->auth->registrar($request->validated());

        return response()->json([
            'token' => $resultado['token'],
            'user' => new UserResource($resultado['user']),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $resultado = $this->auth->iniciarSesion(
            $request->validated('correo'),
            $request->validated('password'),
        );

        return response()->json([
            'token' => $resultado['token'],
            'user' => new UserResource($resultado['user']),
        ]);
    }

    /**
     * Respuesta siempre idéntica, exista o no la cuenta (docs §4 A5).
     */
    public function passwordEmail(RecuperarClaveRequest $request): JsonResponse
    {
        $this->auth->enviarEnlace($request->validated('correo'));

        return response()->json([
            'message' => 'Si el correo existe, te enviamos un enlace para restablecer tu contraseña.',
        ]);
    }

    public function passwordReset(RestablecerClaveRequest $request): JsonResponse
    {
        $estado = $this->auth->restablecer(
            [
                'correo' => $request->validated('correo'),
                'password' => $request->validated('password'),
            ],
            $request->validated('token'),
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($estado),
                'errors' => ['token' => [__($estado)]],
            ], 422);
        }

        return response()->json([
            'message' => __(Password::PASSWORD_RESET),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->auth->cerrarSesion($user);

        return response()->json(['message' => 'Cerraste sesión.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
