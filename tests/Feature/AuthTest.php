<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function datosRegistro(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Lucía Ramírez',
            'correo' => 'Lucia.Ramirez@ElHuarike.com',
            'password' => 'reservas2024',
            'password_confirmation' => 'reservas2024',
            'telefono' => '+51 987 654 321',
        ], $overrides);
    }

    public function test_un_cliente_se_registra_y_recibe_su_token(): void
    {
        $respuesta = $this->postJson('/api/v1/auth/register', $this->datosRegistro());

        $respuesta->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'nombre', 'correo', 'telefono', 'rol', 'activa', 'creado_en']])
            ->assertJsonPath('user.rol', 'cliente')
            ->assertJsonPath('user.correo', 'lucia.ramirez@elhuarike.com')
            ->assertJsonPath('user.telefono', '987654321')
            ->assertJsonMissingPath('user.password');

        $user = User::query()->where('correo', 'lucia.ramirez@elhuarike.com')->firstOrFail();

        $this->assertSame(Rol::Cliente, $user->rol);
        $this->assertTrue($user->activa);
        $this->assertTrue(Hash::check('reservas2024', $user->password));
    }

    public function test_el_registro_valida_nombre_correo_clave_y_telefono(): void
    {
        $respuesta = $this->postJson('/api/v1/auth/register', [
            'nombre' => 'Lu',
            'correo' => 'lucia-sin-arroba',
            'password' => 'corta',
            'password_confirmation' => 'corta',
            'telefono' => '12345',
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonPath('message', 'Los datos enviados no son válidos')
            ->assertJsonPath('errors.nombre.0', 'El nombre debe tener al menos 3 caracteres.')
            ->assertJsonPath('errors.correo.0', 'Revisa el correo: falta un formato válido (ejemplo@dominio.com).')
            ->assertJsonPath('errors.password.0', 'La contraseña debe tener al menos 8 caracteres.')
            ->assertJsonPath('errors.telefono.0', 'El teléfono debe tener 9 dígitos (puede incluir +51).');

        $this->assertSame(0, User::query()->count());
    }

    public function test_no_se_puede_registrar_dos_veces_el_mismo_correo(): void
    {
        User::factory()->create(['correo' => 'demo@elhuarike.com']);

        $this->postJson('/api/v1/auth/register', $this->datosRegistro(['correo' => 'demo@elhuarike.com']))
            ->assertStatus(422)
            ->assertJsonPath('errors.correo.0', 'Ese correo ya tiene una cuenta. Inicia sesión.');

        $this->assertSame(1, User::query()->count());
    }

    public function test_el_login_devuelve_token_y_usuario(): void
    {
        User::factory()->create([
            'correo' => 'demo@elhuarike.com',
            'password' => 'reservas2024',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'correo' => '  DEMO@elhuarike.com ',
            'password' => 'reservas2024',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'nombre', 'rol']])
            ->assertJsonPath('user.correo', 'demo@elhuarike.com');
    }

    public function test_el_login_distingue_correo_inexistente_clave_incorrecta_y_cuenta_desactivada(): void
    {
        $this->postJson('/api/v1/auth/login', ['correo' => 'nadie@elhuarike.com', 'password' => 'reservas2024'])
            ->assertStatus(401)
            ->assertJsonPath('campo', 'correo')
            ->assertJsonPath('message', 'No encontramos una cuenta con ese correo.');

        $user = User::factory()->create(['correo' => 'demo@elhuarike.com', 'password' => 'reservas2024']);

        $this->postJson('/api/v1/auth/login', ['correo' => 'demo@elhuarike.com', 'password' => 'otraclave'])
            ->assertStatus(401)
            ->assertJsonPath('campo', 'password');

        $user->forceFill(['activa' => false])->save();

        $this->postJson('/api/v1/auth/login', ['correo' => 'demo@elhuarike.com', 'password' => 'reservas2024'])
            ->assertStatus(401)
            ->assertJsonPath('campo', 'correo')
            ->assertJsonPath('message', 'Tu cuenta está desactivada. Escríbenos para reactivarla.');
    }

    public function test_me_devuelve_la_sesion_sin_datos_sensibles_y_rechaza_sin_token(): void
    {
        $user = User::factory()->create(['correo' => 'demo@elhuarike.com']);
        $token = $user->createToken('spa')->plainTextToken;

        $respuesta = $this->withToken($token)->getJson('/api/v1/auth/me');

        $respuesta->assertOk()
            ->assertJsonPath('data.correo', 'demo@elhuarike.com')
            ->assertJsonPath('data.rol', 'cliente')
            ->assertJsonMissingPath('data.password')
            ->assertJsonStructure(['data' => ['id', 'nombre', 'correo', 'telefono', 'rol', 'activa', 'creado_en']]);

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Tu sesión expiró. Inicia sesión de nuevo.');
    }

    public function test_logout_revoca_el_token_actual(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_login_esta_limitado_a_cinco_intentos_por_minuto(): void
    {
        User::factory()->create(['correo' => 'demo@elhuarike.com', 'password' => 'reservas2024']);

        for ($intento = 1; $intento <= 5; $intento++) {
            $this->postJson('/api/v1/auth/login', [
                'correo' => 'demo@elhuarike.com',
                'password' => 'incorrecta',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'correo' => 'demo@elhuarike.com',
            'password' => 'incorrecta',
        ])->assertStatus(429);
    }
}
