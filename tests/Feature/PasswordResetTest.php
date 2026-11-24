<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE_GENERICO = 'Si el correo existe, te enviamos un enlace para restablecer tu contraseña.';

    public function test_envia_el_enlace_a_una_cuenta_existente(): void
    {
        Notification::fake();

        $user = User::factory()->create(['correo' => 'demo@elhuarike.com']);

        $this->postJson('/api/v1/auth/password/email', ['correo' => 'demo@elhuarike.com'])
            ->assertOk()
            ->assertJsonPath('message', self::MENSAJE_GENERICO);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_la_respuesta_es_identica_para_un_correo_inexistente(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/email', ['correo' => 'nadie@elhuarike.com'])
            ->assertOk()
            ->assertJsonPath('message', self::MENSAJE_GENERICO);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_restablecer_cambia_la_clave_y_revoca_los_tokens_activos(): void
    {
        $user = User::factory()->create([
            'correo' => 'demo@elhuarike.com',
            'password' => 'reservas2024',
        ]);
        $tokenViejo = $user->createToken('spa')->plainTextToken;
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'correo' => 'demo@elhuarike.com',
            'password' => 'nuevaClave2026',
            'password_confirmation' => 'nuevaClave2026',
        ])->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('nuevaClave2026', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenViejo)->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'correo' => 'demo@elhuarike.com',
            'password' => 'nuevaClave2026',
        ])->assertOk();
    }

    public function test_un_token_invalido_no_restablece_la_clave(): void
    {
        $user = User::factory()->create([
            'correo' => 'demo@elhuarike.com',
            'password' => 'reservas2024',
        ]);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'token-inventado',
            'correo' => 'demo@elhuarike.com',
            'password' => 'nuevaClave2026',
            'password_confirmation' => 'nuevaClave2026',
        ])->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'El enlace para restablecer la contraseña no es válido o ya expiró.');

        $this->assertTrue(Hash::check('reservas2024', $user->fresh()->password));
    }

    public function test_la_clave_nueva_debe_cumplir_el_minimo_y_coincidir(): void
    {
        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'cualquiera',
            'correo' => 'demo@elhuarike.com',
            'password' => 'corta',
            'password_confirmation' => 'distinta',
        ])->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'La contraseña debe tener al menos 8 caracteres.')
            ->assertJsonPath('errors.password.1', 'Las contraseñas no coinciden.');
    }
}
