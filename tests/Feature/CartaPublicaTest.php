<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Plato;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartaPublicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_carta_publica_trae_solo_categorias_activas_con_su_resumen(): void
    {
        $this->seed(DatabaseSeeder::class);

        $respuesta = $this->getJson('/api/v1/carta')->assertOk();

        $respuesta->assertJsonPath('resumen.categorias', 8)
            ->assertJsonPath('resumen.platos', 22)
            ->assertJsonPath('resumen.disponibles', 21)
            ->assertJsonPath('resumen.agotados', 1)
            ->assertJsonCount(8, 'categorias')
            ->assertJsonStructure([
                'categorias' => [[
                    'id', 'nombre', 'posicion', 'platos' => [['id', 'nombre', 'precio', 'etiquetas', 'disponible']],
                ]],
            ]);

        // "Chaufa de mariscos" está agotado pero se muestra con su bandera.
        $platos = collect($respuesta->json('categorias'))->flatMap(fn ($categoria) => $categoria['platos']);
        $chaufa = $platos->firstWhere('nombre', 'Chaufa de mariscos');

        $this->assertNotNull($chaufa);
        $this->assertFalse($chaufa['disponible']);
        $this->assertSame(42.0, (float) $chaufa['precio']);
    }

    public function test_una_categoria_inactiva_no_aparece_ni_suma_en_el_resumen(): void
    {
        $this->seed(DatabaseSeeder::class);

        $postres = Categoria::query()->where('nombre', 'Postres')->firstOrFail();
        $postres->forceFill(['activa' => false])->save();

        $respuesta = $this->getJson('/api/v1/carta')->assertOk();

        $respuesta->assertJsonPath('resumen.categorias', 7)
            ->assertJsonPath('resumen.platos', 20);

        $nombres = collect($respuesta->json('categorias'))->pluck('nombre')->all();
        $this->assertNotContains('Postres', $nombres);
    }

    public function test_los_platos_eliminados_no_se_listan(): void
    {
        $this->seed(DatabaseSeeder::class);

        Plato::query()->where('nombre', 'Picarones')->firstOrFail()->delete();

        $platos = collect($this->getJson('/api/v1/carta')->json('categorias'))
            ->flatMap(fn ($categoria) => $categoria['platos'])
            ->pluck('nombre')
            ->all();

        $this->assertNotContains('Picarones', $platos);
    }

    public function test_las_categorias_se_ordenan_por_posicion(): void
    {
        $this->seed(DatabaseSeeder::class);

        $posiciones = collect($this->getJson('/api/v1/carta')->json('categorias'))->pluck('posicion')->all();
        $ordenadas = $posiciones;
        sort($ordenadas);

        $this->assertSame($ordenadas, $posiciones);
        $this->assertSame(count(CatalogoSeeder::CARTA), count($posiciones));
    }
}
