<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Plato;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelCartaTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->token = User::query()
            ->where('correo', 'ana.quispe@elhuarike.pe')
            ->firstOrFail()
            ->createToken('spa')
            ->plainTextToken;
    }

    public function test_la_carta_del_panel_incluye_inactivas_y_agotados(): void
    {
        Categoria::query()->where('nombre', 'Postres')->firstOrFail()->forceFill(['activa' => false])->save();

        $respuesta = $this->withToken($this->token)->getJson('/api/v1/panel/carta')->assertOk();

        $respuesta->assertJsonPath('resumen.categorias', 8)
            ->assertJsonPath('resumen.agotados', 1);

        $nombres = collect($respuesta->json('categorias'))->pluck('nombre')->all();
        $this->assertContains('Postres', $nombres);
    }

    public function test_crea_una_categoria_con_la_posicion_siguiente(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/panel/categorias', ['nombre' => 'Cafés'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Cafés')
            ->assertJsonPath('data.activa', true)
            ->assertJsonPath('data.posicion', 9);
    }

    public function test_no_permite_dos_categorias_con_el_mismo_nombre(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/panel/categorias', ['nombre' => 'Postres'])
            ->assertStatus(422)
            ->assertJsonPath('errors.nombre.0', 'Ya existe una categoría con ese nombre.');
    }

    public function test_activar_y_desactivar_una_categoria(): void
    {
        $categoria = Categoria::query()->where('nombre', 'Bebidas')->firstOrFail();

        $this->withToken($this->token)->patchJson("/api/v1/panel/categorias/{$categoria->id}", [
            'nombre' => 'Bebidas',
            'activa' => false,
        ])->assertOk()->assertJsonPath('data.activa', false);

        $this->assertFalse($categoria->fresh()->activa);
    }

    public function test_no_elimina_una_categoria_con_platos_activos(): void
    {
        $categoria = Categoria::query()->where('nombre', 'Cebiches y tiraditos')->firstOrFail();

        $this->withToken($this->token)->deleteJson("/api/v1/panel/categorias/{$categoria->id}")
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'categoria-con-platos');

        $this->assertNotSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    public function test_elimina_con_soft_delete_una_categoria_sin_platos_activos(): void
    {
        $categoria = Categoria::create(['nombre' => 'Temporada', 'posicion' => 20, 'activa' => true]);
        Plato::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Plato retirado',
            'precio' => 10,
            'etiquetas' => [],
            'disponible' => false,
        ]);

        $this->withToken($this->token)->deleteJson("/api/v1/panel/categorias/{$categoria->id}")
            ->assertOk();

        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
        $this->assertNull(Categoria::query()->find($categoria->id));
    }

    public function test_crea_un_plato_validando_categoria_nombre_y_precio(): void
    {
        $categoria = Categoria::query()->where('nombre', 'Postres')->firstOrFail();

        $this->withToken($this->token)->postJson('/api/v1/panel/platos', [
            'categoria_id' => $categoria->id,
            'nombre' => 'Torta de chocolate',
            'descripcion' => 'Porción con ganache.',
            'precio' => 21.5,
            'etiquetas' => ['Vegetariano'],
        ])->assertCreated()
            ->assertJsonPath('data.nombre', 'Torta de chocolate')
            ->assertJsonPath('data.precio', 21.5)
            ->assertJsonPath('data.disponible', true)
            ->assertJsonPath('data.etiquetas', ['Vegetariano']);
    }

    public function test_los_mensajes_de_validacion_del_plato_son_especificos(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/panel/platos', [
            'nombre' => 'ab',
            'precio' => 0,
        ])->assertStatus(422)
            ->assertJsonPath('errors.categoria_id.0', 'Elige una categoría.')
            ->assertJsonPath('errors.nombre.0', 'El nombre debe tener al menos 3 caracteres.')
            ->assertJsonPath('errors.precio.0', 'El precio debe ser mayor a 0.');
    }

    public function test_el_precio_no_acepta_valores_no_numericos(): void
    {
        $categoria = Categoria::query()->firstOrFail();

        $this->withToken($this->token)->postJson('/api/v1/panel/platos', [
            'categoria_id' => $categoria->id,
            'nombre' => 'Plato raro',
            'precio' => 'gratis',
        ])->assertStatus(422)
            ->assertJsonPath('errors.precio.0', 'El precio debe ser un número.');
    }

    public function test_toggle_de_disponible_marca_el_plato_como_agotado(): void
    {
        $plato = Plato::query()->where('nombre', 'Cebiche clásico')->firstOrFail();

        $this->withToken($this->token)->patchJson("/api/v1/panel/platos/{$plato->id}", [
            'categoria_id' => $plato->categoria_id,
            'nombre' => $plato->nombre,
            'precio' => 45,
            'disponible' => false,
        ])->assertOk()->assertJsonPath('data.disponible', false);

        $this->assertFalse($plato->fresh()->disponible);
    }

    public function test_no_permite_repetir_el_nombre_de_plato_en_la_misma_categoria(): void
    {
        $plato = Plato::query()->where('nombre', 'Picarones')->firstOrFail();

        $this->withToken($this->token)->postJson('/api/v1/panel/platos', [
            'categoria_id' => $plato->categoria_id,
            'nombre' => 'Picarones',
            'precio' => 16,
        ])->assertStatus(422)
            ->assertJsonPath('errors.nombre.0', 'Ya existe un plato con ese nombre en la categoría.');
    }

    public function test_elimina_un_plato_con_soft_delete(): void
    {
        $plato = Plato::query()->where('nombre', 'Limonada frozen')->firstOrFail();

        $this->withToken($this->token)->deleteJson("/api/v1/panel/platos/{$plato->id}")->assertOk();

        $this->assertSoftDeleted('platos', ['id' => $plato->id]);
    }

    public function test_un_cliente_no_puede_tocar_la_carta(): void
    {
        $cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();
        $token = $cliente->createToken('spa')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/panel/carta')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/panel/categorias', ['nombre' => 'Otra'])->assertStatus(403);
    }
}
