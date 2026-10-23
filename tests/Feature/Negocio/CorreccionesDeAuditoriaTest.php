<?php

namespace Tests\Feature\Negocio;

use App\Models\Asistencia;
use App\Models\Bitacora;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\User;
use App\Services\ServicioAsistencia;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Pruebas permanentes de los problemas que halló la auditoría (cada una falla si el defecto vuelve). */
class CorreccionesDeAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    private function persona(): Personal
    {
        $persona = Personal::create([
            'dni' => '71234567', 'usertype_id' => TipoPersonal::ayudante()->id, 'name' => 'Ana', 'last_name' => 'Prueba',
            'birthdate' => '1990-01-01', 'email' => 'ana@prueba.test', 'address' => 'Av. Principal 123, Chiclayo',
            'password' => 'secret123', 'status' => true,
        ]);
        Contrato::create(['user_id' => $persona->id, 'type' => Contrato::PERMANENTE, 'start_date' => today()->subMonths(3)->toDateString(), 'salary' => 1500, 'is_active' => true]);

        return $persona;
    }

    public function test_dos_marcaciones_seguidas_dan_entrada_y_salida_nunca_dos_entradas(): void
    {
        // Las dos marcaciones deben estar en el pasado, aunque la suite se ejecute por la mañana.
        $this->travelTo(today()->setTime(13, 0));
        $persona = $this->persona();
        $momento = Carbon::today()->setTime(9, 0);

        $primera = app(ServicioAsistencia::class)->registrar($persona, $momento);
        $segunda = app(ServicioAsistencia::class)->registrar($persona, $momento->copy()->addHours(2));

        $this->assertSame('entrada', $primera->fresh()->type);
        $this->assertSame('salida', $segunda->fresh()->type);
        $this->assertSame(1, Asistencia::where('type', 'entrada')->where('user_id', $persona->id)->count());
    }

    public function test_los_cambios_importantes_quedan_en_la_bitacora(): void
    {
        $this->persona();

        $this->assertTrue(Bitacora::where('model', 'Personal')->where('action', 'creado')->exists());
        $this->assertTrue(Bitacora::where('model', 'Contrato')->where('user_id', $this->admin->id)->orWhere('model', 'Contrato')->exists());

        $this->getJson(route('admin.bitacora.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonStructure(['data' => [['fecha', 'usuario', 'accion', 'registro', 'detalle']]]);
    }

    public function test_el_listado_de_asistencias_se_pagina_en_el_servidor(): void
    {
        $persona = $this->persona();
        foreach (range(1, 15) as $dia) {
            Asistencia::create([
                'user_id' => $persona->id, 'schedule_id' => Turno::first()->id, 'date' => today()->toDateString(), 'shift_date' => today()->toDateString(),
                'time' => sprintf('%02d:00:00', $dia % 24), 'status' => 'presente', 'source' => 'admin', 'type' => null,
            ]);
        }

        $respuesta = $this->getJson(route('admin.asistencias.index', ['draw' => 1, 'start' => 0, 'length' => 10]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertCount(10, $respuesta->json('data'));
        $this->assertSame(15, $respuesta->json('recordsTotal'));
    }

    public function test_conductor_y_ayudante_no_se_eliminan(): void
    {
        $this->deleteJson(route('admin.tipospersonal.destroy', TipoPersonal::conductor()->id), $this->versionDe(route('admin.tipospersonal.destroy', TipoPersonal::conductor()->id)))->assertStatus(422);
    }

    /* ---------------------------- usuarios del sistema ---------------------------- */

    public function test_se_crea_un_administrador_con_contrasena_fuerte(): void
    {
        $this->postJson(route('admin.usuarios.store'), ['name' => 'Otro Admin', 'email' => 'OTRO@rsu.test', 'password' => '12345678', 'password_confirmation' => '12345678', 'status' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson(route('admin.usuarios.store'), ['name' => 'Otro Admin', 'email' => 'OTRO@rsu.test', 'password' => 'Clave1234x', 'password_confirmation' => 'Clave1234x', 'status' => 1])
            ->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'otro@rsu.test', 'status' => true]);
    }

    public function test_no_se_puede_eliminar_ni_desactivar_al_ultimo_administrador_ni_a_uno_mismo(): void
    {
        $this->deleteJson(route('admin.usuarios.destroy', $this->admin->id), $this->versionDe(route('admin.usuarios.destroy', $this->admin->id)))->assertStatus(422);

        $this->putJson(route('admin.usuarios.update', $this->admin->id), $this->versionDe(route('admin.usuarios.update', $this->admin->id)) + (['name' => 'Admin Principal', 'email' => $this->admin->email, 'status' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->assertEquals(1, $this->admin->fresh()->status);
    }

    public function test_un_administrador_inactivo_no_inicia_sesion(): void
    {
        auth()->logout();
        $inactivo = User::factory()->create(['status' => false]);

        $this->post('/login', ['email' => $inactivo->email, 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    /* ---------------------------- recuperación de contraseña ---------------------------- */

    public function test_recuperar_contrasena_no_revela_si_el_correo_existe(): void
    {
        auth()->logout();
        $existente = User::factory()->create();

        $conocido = $this->post('/forgot-password', ['email' => $existente->email]);
        $desconocido = $this->post('/forgot-password', ['email' => 'nadie@rsu.test']);

        $desconocido->assertSessionHasNoErrors();
        $this->assertSame(session('status'), $conocido->getSession()->get('status'));
    }
}
