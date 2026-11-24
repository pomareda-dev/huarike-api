<?php

namespace Tests\Feature;

use App\Models\Promocion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromocionesPublicasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_solo_se_pubican_las_promociones_activas_con_la_destacada_primero(): void
    {
        $respuesta = $this->getJson('/api/v1/promociones')->assertOk();

        $respuesta->assertJsonCount(5, 'promociones')
            ->assertJsonPath('promociones.0.destacada', true)
            ->assertJsonPath('promociones.0.codigo', 'CEBICHE2X1')
            ->assertJsonPath('filtros', ['desayunos', 'almuerzos', 'familia'])
            ->assertJsonStructure(['promociones' => [[
                'id', 'nombre', 'slug', 'destacada', 'vigencia', 'categoria',
                'condiciones', 'codigo', 'precio_texto', 'foto', 'activa',
            ]]]);
    }

    public function test_una_promocion_inactiva_no_aparece_en_el_sitio(): void
    {
        Promocion::query()->where('codigo', 'CUMPLE')->firstOrFail()
            ->forceFill(['activa' => false])->save();

        $respuesta = $this->getJson('/api/v1/promociones')->assertOk();

        $respuesta->assertJsonCount(4, 'promociones');

        $codigos = collect($respuesta->json('promociones'))->pluck('codigo')->all();
        $this->assertNotContains('CUMPLE', $codigos);
    }

    public function test_filtra_por_categoria(): void
    {
        $respuesta = $this->getJson('/api/v1/promociones?categoria=desayunos')->assertOk();

        $respuesta->assertJsonCount(1, 'promociones')
            ->assertJsonPath('promociones.0.codigo', 'DESAYUNO19');

        $familias = $this->getJson('/api/v1/promociones?categoria=familia')->assertOk();
        $familias->assertJsonCount(2, 'promociones');
    }

    public function test_una_categoria_desconocida_no_filtra_nada(): void
    {
        $this->getJson('/api/v1/promociones?categoria=postres')
            ->assertOk()
            ->assertJsonCount(5, 'promociones');
    }
}
