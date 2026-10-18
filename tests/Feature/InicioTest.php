<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pantalla de bienvenida: accesos rápidos, reloj del servidor y acceso a la Marcación DNI. */
class InicioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    public function test_muestra_la_bienvenida_con_sus_accesos(): void
    {
        $this->get(route('admin.inicio'))
            ->assertOk()
            ->assertSee('¡Bienvenido al Sistema RSU!')
            ->assertSee(config('proyecto.municipalidad'))
            ->assertSee(route('admin.personal.index'))
            ->assertSee(route('admin.contratos.index'))
            ->assertSee('Próximamente');
    }

    public function test_tiene_reloj_y_acceso_a_la_marcacion_dni(): void
    {
        $this->get(route('admin.inicio'))
            ->assertOk()
            ->assertSee(route('admin.asistencias.marcacion'))
            ->assertSee(route('admin.asistencias.marcacion.estado'));
    }

    public function test_el_reloj_recibe_la_hora_y_el_turno_del_servidor(): void
    {
        $this->travelTo(today()->setTime(15, 0));

        $this->getJson(route('admin.asistencias.marcacion.estado'))
            ->assertOk()
            ->assertJsonPath('zona', config('app.timezone'))
            ->assertJsonPath('turno', 'Tarde (14:00 - 22:00)');
    }
}
