<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $overrides = []): User
    {
        return User::factory()->admin()->create($overrides);
    }

    public function test_un_cliente_no_entra_al_panel(): void
    {
        $cliente = User::factory()->create();
        $token = $cliente->createToken('spa')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/panel/usuarios')
            ->assertStatus(403)
            ->assertJsonPath('message', 'No tienes permisos para esta acción.');
    }

    public function test_el_listado_pagina_de_seis_en_seis_y_filtra_por_rol_estado_y_texto(): void
    {
        $admin = $this->admin(['nombre' => 'Ana Quispe', 'correo' => 'ana.quispe@elhuarike.pe']);
        $token = $admin->createToken('spa')->plainTextToken;

        User::factory()->count(7)->create();
        User::factory()->create(['nombre' => 'Rocío Vega', 'correo' => 'rocio.vega@correo.pe']);
        User::factory()->inactiva()->create(['nombre' => 'Jorge Salas', 'correo' => 'jorge.salas@correo.pe']);

        $this->withToken($token)->getJson('/api/v1/panel/usuarios')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('meta.pagina', 1)
            ->assertJsonPath('meta.por_pagina', 6)
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('meta.total_paginas', 2)
            ->assertJsonPath('meta.desde', 1)
            ->assertJsonPath('meta.hasta', 6)
            ->assertJsonMissingPath('data.0.password');

        $this->withToken($token)->getJson('/api/v1/panel/usuarios?page=2')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        // Filtro por rol.
        $this->withToken($token)->getJson('/api/v1/panel/usuarios?rol=admin')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Filtro por estado.
        $this->withToken($token)->getJson('/api/v1/panel/usuarios?estado=inactiva')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.correo', 'jorge.salas@correo.pe');

        // Búsqueda sin tildes ni mayúsculas ("rocio" encuentra a "Rocío").
        $this->withToken($token)->getJson('/api/v1/panel/usuarios?texto=ROCIO')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nombre', 'Rocío Vega');
    }

    public function test_el_resumen_cuenta_total_administradores_e_inactivas(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('spa')->plainTextToken;

        User::factory()->count(2)->create();
        User::factory()->inactiva()->create();

        $this->withToken($token)->getJson('/api/v1/panel/usuarios/resumen')
            ->assertOk()
            ->assertExactJson(['total' => 4, 'administradores' => 1, 'inactivas' => 1]);
    }

    public function test_el_admin_crea_usuarios_con_rol_elegido(): void
    {
        $token = $this->admin()->createToken('spa')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/panel/usuarios', [
            'nombre' => 'Marco Chávez',
            'correo' => 'Marco.Chavez@Correo.pe',
            'telefono' => '988 123 456',
            'rol' => 'admin',
            'password' => 'panel2026',
            'password_confirmation' => 'panel2026',
        ])->assertCreated()
            ->assertJsonPath('data.correo', 'marco.chavez@correo.pe')
            ->assertJsonPath('data.rol', 'admin')
            ->assertJsonPath('data.telefono', '988123456')
            ->assertJsonPath('data.activa', true);
    }

    public function test_un_admin_no_puede_desactivar_su_propia_cuenta(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('spa')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/panel/usuarios/{$admin->id}/desactivar")
            ->assertStatus(409)
            ->assertJsonPath('message', 'No puedes desactivar tu propia cuenta.')
            ->assertJsonPath('motivo', 'autodesactivacion');

        $this->assertTrue($admin->fresh()->activa);
    }

    public function test_no_queda_el_sistema_sin_administradores_activos(): void
    {
        $admin = $this->admin();
        $otro = $this->admin();
        $token = $otro->createToken('spa')->plainTextToken;

        // Con dos admins activos, desactivar al otro está permitido.
        $this->withToken($token)->patchJson("/api/v1/panel/usuarios/{$admin->id}/desactivar")
            ->assertOk()
            ->assertJsonPath('data.activa', false);

        // Ahora $otro es el último admin activo: no puede quitarse el rol ni
        // ser desactivado por un tercero.
        $tercero = $this->admin();
        $tokenTercero = $tercero->createToken('spa')->plainTextToken;

        $this->withToken($tokenTercero)->patchJson("/api/v1/panel/usuarios/{$otro->id}/rol", ['rol' => 'cliente'])
            ->assertOk();

        $this->withToken($tokenTercero)->patchJson("/api/v1/panel/usuarios/{$tercero->id}/rol", ['rol' => 'cliente'])
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'ultimo-admin');

        $this->assertSame(Rol::Admin, $tercero->fresh()->rol);
    }

    public function test_desactivar_revoca_los_tokens_de_la_cuenta(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('spa')->plainTextToken;

        $cliente = User::factory()->create();
        $tokenCliente = $cliente->createToken('spa')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/panel/usuarios/{$cliente->id}/desactivar")->assertOk();

        $this->assertSame(0, $cliente->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenCliente)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_activar_una_cuenta_la_deja_operativa(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('spa')->plainTextToken;
        $inactiva = User::factory()->inactiva()->create();

        $this->withToken($token)->patchJson("/api/v1/panel/usuarios/{$inactiva->id}/activar")
            ->assertOk()
            ->assertJsonPath('data.activa', true);
    }

    public function test_el_csv_de_usuarios_tiene_cabecera_espanola_y_bom(): void
    {
        $admin = User::factory()->admin()->create([
            'nombre' => 'Ana Quispe',
            'correo' => 'ana.quispe@elhuarike.pe',
        ]);
        $token = $admin->createToken('spa')->plainTextToken;

        $respuesta = $this->withToken($token)->get('/api/v1/panel/usuarios/export.csv');

        $respuesta->assertOk();

        $contenido = $respuesta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('Alta,Usuario,Correo,Rol,Estado', $contenido);
        $this->assertStringContainsString('"Ana Quispe",ana.quispe@elhuarike.pe,Administrador,Activa', $contenido);
    }
}
