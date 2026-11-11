<?php

namespace Tests\Feature\Api;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\Recorrido;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Zona;
use Database\Seeders\ColorVehiculoSeeder;
use Database\Seeders\MarcaSeeder;
use Database\Seeders\ModeloSeeder;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TipoVehiculoSeeder;
use Database\Seeders\TurnoSeeder;
use Database\Seeders\VehiculoSeeder;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Avance 6: API de la app móvil del conductor (login, programaciones, recorrido con GPS e historial). */
class ConductorApiTest extends TestCase
{
    use RefreshDatabase;

    private Turno $manana;

    private Turno $noche;

    private int $dni = 72000000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class, ZonaSeeder::class, MarcaSeeder::class, ModeloSeeder::class,
            TipoVehiculoSeeder::class, ColorVehiculoSeeder::class, VehiculoSeeder::class]);

        // Hoy a las 07:00: el turno Mañana (06:00-14:00) está en curso.
        $this->travelTo(Carbon::parse('2026-11-10 07:00:00'));

        $this->manana = Turno::firstWhere('name', 'Mañana');
        $this->noche = Turno::firstWhere('name', 'Noche');
    }

    private function persona(string $funcion = 'conductor', array $cambios = []): Personal
    {
        $tipo = $funcion === 'conductor' ? TipoPersonal::conductor() : TipoPersonal::ayudante();
        $this->dni++;

        $persona = Personal::create($cambios + [
            'dni' => (string) $this->dni, 'usertype_id' => $tipo->id, 'name' => ucfirst($funcion), 'last_name' => "Prueba {$this->dni}",
            'birthdate' => '1990-01-01', 'email' => "p{$this->dni}@prueba.test", 'address' => 'Av. Principal 123, Chiclayo',
            'password' => 'secret123', 'status' => true, 'license' => $funcion === 'conductor' ? 'Q'.$this->dni : null,
        ]);
        Contrato::create(['user_id' => $persona->id, 'type' => Contrato::PERMANENTE, 'start_date' => today()->subYear()->toDateString(), 'salary' => 1500, 'is_active' => true]);

        return $persona;
    }

    private function programacion(Personal $conductor, array $ayudantes, ?Turno $turno = null, ?string $fecha = null, int $vehiculo = 0): Programacion
    {
        $programacion = Programacion::create([
            'date' => $fecha ?? today()->toDateString(), 'zone_id' => Zona::orderBy('id')->value('id'), 'shift_id' => ($turno ?? $this->manana)->id,
            'vehicle_id' => Vehiculo::orderBy('id')->skip($vehiculo)->value('id'), 'driver_id' => $conductor->id, 'status' => Programacion::PROGRAMADO,
        ]);
        $programacion->asignarAyudantes(array_map(fn (Personal $p) => $p->id, $ayudantes));

        return $programacion->fresh();
    }

    private function marcar(Personal $persona, ?Turno $turno = null, ?string $jornada = null): void
    {
        $turno ??= $this->manana;
        $jornada ??= today()->toDateString();

        Asistencia::create([
            'user_id' => $persona->id, 'date' => $jornada, 'time' => substr($turno->time_start, 0, 5).':00',
            'schedule_id' => $turno->id, 'shift_date' => $jornada, 'type' => Asistencia::ENTRADA,
            'status' => Asistencia::PRESENTE, 'source' => 'admin',
        ]);
    }

    private function token(Personal $conductor): string
    {
        return $this->postJson('/api/conductor/login', ['email' => $conductor->email, 'password' => 'secret123'])
            ->assertOk()->json('token');
    }

    private function api(string $metodo, string $url, string $token, array $datos = [])
    {
        // En las pruebas la aplicación conserva el usuario entre peticiones: se olvida para que cada token cuente.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->{$metodo.'Json'}('/api/conductor/'.$url, $datos);
    }

    /** Equipo completo en el turno Mañana de hoy, con asistencia, y su token. */
    private function equipoListo(): array
    {
        $conductor = $this->persona();
        $ayudantes = [$this->persona('ayudante'), $this->persona('ayudante')];
        $p = $this->programacion($conductor, $ayudantes);
        foreach ([$conductor, ...$ayudantes] as $persona) {
            $this->marcar($persona);
        }

        return [$conductor, $p, $this->token($conductor)];
    }

    /* ------------------------------ acceso ------------------------------ */

    public function test_el_conductor_ingresa_con_su_correo_y_clave(): void
    {
        $conductor = $this->persona();

        $this->postJson('/api/conductor/login', ['email' => strtoupper($conductor->email), 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'conductor' => ['id', 'nombre_completo', 'dni'], 'config' => ['segundos_entre_puntos']])
            ->assertJsonPath('conductor.dni', $conductor->dni);
    }

    public function test_rechaza_clave_errada_ayudantes_inactivos_y_administradores(): void
    {
        $conductor = $this->persona();
        $this->postJson('/api/conductor/login', ['email' => $conductor->email, 'password' => 'otra-clave'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $ayudante = $this->persona('ayudante');
        $this->postJson('/api/conductor/login', ['email' => $ayudante->email, 'password' => 'secret123'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'Esta aplicación es solo para conductores.');

        $inactivo = $this->persona('conductor', ['status' => false]);
        $this->postJson('/api/conductor/login', ['email' => $inactivo->email, 'password' => 'secret123'])->assertStatus(422);

        $admin = User::factory()->create(['password' => 'secret123']);
        $this->postJson('/api/conductor/login', ['email' => $admin->email, 'password' => 'secret123'])->assertStatus(422);
    }

    public function test_sin_token_no_hay_acceso_y_el_token_del_conductor_no_abre_el_panel(): void
    {
        $this->getJson('/api/conductor/programaciones')->assertUnauthorized();

        $token = $this->token($this->persona());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/admin')->assertForbidden();
        $this->withToken($token)->get('/admin')->assertForbidden();
        $this->withToken($token)->getJson('/user/profile')->assertForbidden();
    }

    public function test_un_conductor_dado_de_baja_pierde_el_acceso(): void
    {
        $conductor = $this->persona();
        $token = $this->token($conductor);
        $conductor->update(['status' => false]);

        $this->api('get', 'perfil', $token)->assertUnauthorized();
    }

    public function test_cerrar_sesion_invalida_el_token(): void
    {
        $token = $this->token($this->persona());

        $this->api('post', 'logout', $token)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /* ------------------------------ programaciones ------------------------------ */

    public function test_mis_programaciones_lista_solo_las_propias_y_pendientes_por_fecha(): void
    {
        $conductor = $this->persona();
        $otro = $this->persona();
        $ayudante = $this->persona('ayudante');

        $this->programacion($conductor, [$ayudante], $this->manana, today()->addDays(2)->toDateString());
        $this->programacion($conductor, [], $this->manana, today()->toDateString());
        $this->programacion($conductor, [], $this->manana, today()->subDays(3)->toDateString());   // pasada
        $this->programacion($otro, [], $this->manana, today()->addDay()->toDateString(), 1);         // de otro conductor

        $respuesta = $this->api('get', 'programaciones', $this->token($conductor))->assertOk();

        $this->assertSame([today()->toDateString(), today()->addDays(2)->toDateString()], array_column($respuesta->json('data'), 'fecha'));
        $respuesta->assertJsonPath('data.0.turno.hora_inicio', '06:00')
            ->assertJsonPath('conductor.dni', $conductor->dni);
    }

    public function test_hoy_solo_permite_iniciar_cuando_todos_asistieron(): void
    {
        $conductor = $this->persona();
        $ayudante = $this->persona('ayudante');
        $p = $this->programacion($conductor, [$ayudante]);
        $this->marcar($conductor);
        $token = $this->token($conductor);

        $this->api('get', 'programaciones/hoy', $token)->assertOk()
            ->assertJsonPath('data.id', $p->id)
            ->assertJsonPath('data.todos_presentes', false)
            ->assertJsonPath('data.puede_iniciar', false)
            ->assertJsonPath('data.asistencia.0.rol', 'conductor')
            ->assertJsonPath('data.asistencia.1.presente', false);

        $this->api('post', "programaciones/{$p->id}/recorrido", $token)->assertStatus(422);

        $this->marcar($ayudante);

        $this->api('get', 'programaciones/hoy', $token)->assertJsonPath('data.puede_iniciar', true);
    }

    public function test_la_asistencia_de_otro_turno_no_habilita_el_recorrido(): void
    {
        $conductor = $this->persona();
        $p = $this->programacion($conductor, []);
        $this->marcar($conductor, $this->noche);

        $this->api('get', 'programaciones/hoy', $this->token($conductor))->assertJsonPath('data.puede_iniciar', false);
    }

    public function test_el_nocturno_de_ayer_sigue_siendo_la_programacion_de_hoy_hasta_que_termina(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 03:00:00'));
        $conductor = $this->persona();
        $ayer = today()->subDay()->toDateString();
        $p = $this->programacion($conductor, [], $this->noche, $ayer);
        $this->marcar($conductor, $this->noche, $ayer);

        $this->api('get', 'programaciones/hoy', $this->token($conductor))
            ->assertJsonPath('data.id', $p->id)
            ->assertJsonPath('data.puede_iniciar', true);
    }

    public function test_no_se_puede_iniciar_mucho_antes_del_turno(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 04:00:00'));
        [$conductor, $p, $token] = $this->equipoListo();

        $this->api('get', 'programaciones/hoy', $token)
            ->assertJsonPath('data.puede_iniciar', false)
            ->assertJsonPath('data.motivo', 'Podrá iniciar el recorrido desde las 05:30 (10/11).');
    }

    public function test_no_ve_la_programacion_de_otro_conductor(): void
    {
        $otra = $this->programacion($this->persona(), []);

        $this->api('get', "programaciones/{$otra->id}", $this->token($this->persona()))->assertNotFound();
    }

    /* ------------------------------ recorrido ------------------------------ */

    public function test_recorrido_completo_iniciar_registrar_puntos_finalizar_e_historial(): void
    {
        [$conductor, $p, $token] = $this->equipoListo();

        $id = $this->api('post', "programaciones/{$p->id}/recorrido", $token)->assertCreated()
            ->assertJsonPath('data.estado', Recorrido::EN_CURSO)->json('data.id');

        // No se inicia dos veces.
        $this->api('post', "programaciones/{$p->id}/recorrido", $token)->assertStatus(422);
        $this->api('get', 'recorridos/actual', $token)->assertJsonPath('data.id', $id);
        $this->api('get', 'programaciones/hoy', $token)->assertJsonPath('data.recorrido.id', $id);

        $this->travel(5)->seconds();
        $this->api('post', "recorridos/{$id}/puntos", $token, ['puntos' => [
            ['lat' => -6.7775, 'lng' => -79.8401, 'precision' => 8, 'momento' => now()->subSeconds(5)->toIso8601String()],
            ['lat' => -6.7779, 'lng' => -79.8405, 'momento' => now()->toIso8601String()],
            ['lat' => -6.7, 'lng' => -79.8, 'momento' => now()->subHours(3)->toIso8601String()],   // antes del inicio: se descarta
        ]])->assertOk()->assertExactJson(['registrados' => 2, 'descartados' => 1]);

        $this->api('post', "recorridos/{$id}/puntos", $token, ['puntos' => [['lat' => 200, 'lng' => 0, 'momento' => now()->toIso8601String()]]])
            ->assertStatus(422);

        // La observación es obligatoria.
        $this->api('post', "recorridos/{$id}/finalizar", $token, ['observacion' => ''])->assertStatus(422)->assertJsonValidationErrors('observacion');
        $this->api('post', "recorridos/{$id}/finalizar", $token, ['observacion' => str_repeat('a', 501)])->assertStatus(422);

        $this->travel(2)->hours();
        $this->api('post', "recorridos/{$id}/finalizar", $token, ['observacion' => 'Sin incidencias.'])->assertOk()
            ->assertJsonPath('data.estado', Recorrido::COMPLETADO)
            ->assertJsonPath('data.observacion', 'Sin incidencias.');

        // Ya cerrado: no recibe puntos ni se vuelve a finalizar.
        $this->api('post', "recorridos/{$id}/puntos", $token, ['puntos' => [['lat' => -6.7, 'lng' => -79.8, 'momento' => now()->toIso8601String()]]])->assertStatus(422);
        $this->api('post', "recorridos/{$id}/finalizar", $token, ['observacion' => 'Otra vez'])->assertStatus(422);

        $this->api('get', 'recorridos', $token)->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.estado_texto', 'Completado');

        $detalle = $this->api('get', "recorridos/{$id}", $token)->assertOk();
        $this->assertCount(2, $detalle->json('data.puntos'));
        $this->assertCount(2, $detalle->json('data.ayudantes'));
        $this->assertNotEmpty($detalle->json('data.zona_vertices'));
        $this->assertSame('09:00', Carbon::parse($detalle->json('data.fin'))->format('H:i'));
    }

    public function test_otro_conductor_no_puede_tocar_el_recorrido_ajeno(): void
    {
        [, $p, $token] = $this->equipoListo();
        $id = $this->api('post', "programaciones/{$p->id}/recorrido", $token)->json('data.id');

        $intruso = $this->token($this->persona());

        $this->api('get', "recorridos/{$id}", $intruso)->assertNotFound();
        $this->api('post', "recorridos/{$id}/puntos", $intruso, ['puntos' => [['lat' => -6.7, 'lng' => -79.8, 'momento' => now()->toIso8601String()]]])->assertNotFound();
        $this->api('post', "recorridos/{$id}/finalizar", $intruso, ['observacion' => 'Hackeo'])->assertNotFound();
    }
}
