<?php

namespace Tests\Feature;

use App\Models\Plato;
use App\Models\Promocion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PanelPromocionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->admin = User::query()->where('correo', 'ana.quispe@elhuarike.pe')->firstOrFail();
    }

    private function datos(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Noche de cebiches',
            'vigencia' => 'Viernes de noviembre',
            'categoria' => 'almuerzos',
            'condiciones' => ['Solo con reserva', 'No acumulable'],
            'codigo' => 'noche2x1',
            'precio_texto' => '2×1',
        ], $overrides);
    }

    public function test_crea_una_promocion_con_slug_generado_y_codigo_en_mayusculas(): void
    {
        $this->actuarComo($this->admin)->postJson('/api/v1/panel/promociones', $this->datos())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'noche-de-cebiches')
            ->assertJsonPath('data.codigo', 'NOCHE2X1')
            ->assertJsonPath('data.activa', true)
            ->assertJsonPath('data.destacada', false)
            ->assertJsonPath('data.foto', null);
    }

    public function test_el_slug_no_se_repite(): void
    {
        $this->actuarComo($this->admin)->postJson('/api/v1/panel/promociones', $this->datos())->assertCreated();

        $this->actuarComo($this->admin)
            ->postJson('/api/v1/panel/promociones', $this->datos(['codigo' => 'OTRO']))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'noche-de-cebiches-2');
    }

    public function test_valida_vigencia_categoria_codigo_y_precio(): void
    {
        $this->actuarComo($this->admin)->postJson('/api/v1/panel/promociones', [
            'nombre' => '',
            'categoria' => 'postres',
        ])->assertStatus(422)
            ->assertJsonPath('errors.nombre.0', 'Escribe el nombre de la promoción.')
            ->assertJsonPath('errors.vigencia.0', 'Indica la vigencia visible.')
            ->assertJsonPath('errors.categoria.0', 'Elige una categoría válida.')
            ->assertJsonPath('errors.codigo.0', 'Escribe el código de la promoción.')
            ->assertJsonPath('errors.precio_texto.0', 'Escribe el precio visible.');
    }

    public function test_edita_activar_y_destacar(): void
    {
        $promocion = Promocion::query()->where('codigo', 'EJECUTIVO29')->firstOrFail();

        $this->actuarComo($this->admin)->patchJson("/api/v1/panel/promociones/{$promocion->id}", $this->datos([
            'nombre' => 'Almuerzo ejecutivo S/ 35',
            'codigo' => 'EJECUTIVO35',
            'precio_texto' => 'S/ 35',
            'destacada' => true,
            'activa' => false,
        ]))->assertOk()
            ->assertJsonPath('data.nombre', 'Almuerzo ejecutivo S/ 35')
            ->assertJsonPath('data.destacada', true)
            ->assertJsonPath('data.activa', false)
            ->assertJsonPath('data.slug', 'almuerzo-ejecutivo-s-35');
    }

    public function test_elimina_con_soft_delete(): void
    {
        $promocion = Promocion::query()->where('codigo', 'CUMPLE')->firstOrFail();

        $this->actuarComo($this->admin)->deleteJson("/api/v1/panel/promociones/{$promocion->id}")->assertOk();

        $this->assertSoftDeleted('promociones', ['id' => $promocion->id]);
    }

    public function test_sube_la_foto_con_ruta_publica(): void
    {
        Storage::fake('public');

        $promocion = Promocion::query()->firstOrFail();

        $respuesta = $this->actuarComo($this->admin)->postJson("/api/v1/panel/promociones/{$promocion->id}/foto", [
            'foto' => UploadedFile::fake()->image('promo.jpg', 800, 600),
        ])->assertOk();

        $foto = $respuesta->json('foto');

        $this->assertStringStartsWith('storage/promociones/', $foto);
        $this->assertStringEndsWith('.jpg', $foto);

        $ruta = $promocion->fresh()->foto;
        Storage::disk('public')->assertExists($ruta);
    }

    public function test_rechaza_archivos_que_no_son_imagen_o_pesan_demasiado(): void
    {
        Storage::fake('public');

        $promocion = Promocion::query()->firstOrFail();

        $this->actuarComo($this->admin)->postJson("/api/v1/panel/promociones/{$promocion->id}/foto", [
            'foto' => UploadedFile::fake()->create('carta.pdf', 100, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('errors.foto.0', 'La foto debe ser JPG, PNG o WEBP.');

        $this->actuarComo($this->admin)->postJson("/api/v1/panel/promociones/{$promocion->id}/foto", [
            'foto' => UploadedFile::fake()->image('grande.jpg')->size(5000),
        ])->assertStatus(422)
            ->assertJsonPath('errors.foto.0', 'La foto no debe superar los 4 MB.');
    }

    public function test_sube_la_foto_de_un_plato(): void
    {
        Storage::fake('public');

        $plato = Plato::query()->where('nombre', 'Cebiche clásico')->firstOrFail();

        $respuesta = $this->actuarComo($this->admin)->postJson("/api/v1/panel/platos/{$plato->id}/foto", [
            'foto' => UploadedFile::fake()->image('cebiche.png', 600, 400),
        ])->assertOk();

        $this->assertStringStartsWith('storage/platos/', $respuesta->json('foto'));
        Storage::disk('public')->assertExists($plato->fresh()->foto);
    }

    public function test_un_cliente_no_gestiona_promociones(): void
    {
        $cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();

        $this->actuarComo($cliente)->getJson('/api/v1/panel/promociones')->assertStatus(403);
        $this->actuarComo($cliente)->postJson('/api/v1/panel/promociones', $this->datos())->assertStatus(403);
    }
}
