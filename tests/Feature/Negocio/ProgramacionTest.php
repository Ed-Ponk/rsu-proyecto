<?php

namespace Tests\Feature\Negocio;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Feriado;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\User;
use App\Models\Vacacion;
use App\Services\CargadorFeriados;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Avance 2: turnos, feriados (con carga de un clic) y vacaciones del personal. */
class ProgramacionTest extends TestCase
{
    use RefreshDatabase;

    private int $contadorDni = 30000000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    private function persona(string $contrato = Contrato::PERMANENTE): Personal
    {
        $dni = (string) ++$this->contadorDni;

        $persona = Personal::create([
            'dni' => $dni, 'usertype_id' => TipoPersonal::conductor()->id, 'name' => 'Persona', 'last_name' => "N{$dni}",
            'birthdate' => '1990-01-01', 'email' => "p{$dni}@prueba.test", 'address' => 'Av. Principal 123',
            'password' => 'secret123', 'status' => true, 'license' => 'Q'.substr($dni.'0', 0, 8),
        ]);

        Contrato::create([
            'user_id' => $persona->id, 'type' => $contrato, 'start_date' => today()->subYear()->toDateString(),
            'end_date' => $contrato === Contrato::TEMPORAL ? today()->addMonths(3)->toDateString() : null,
            'salary' => 1500, 'is_active' => true,
        ]);

        return $persona;
    }

    private function ajax(string $metodo, string $url, array $datos = [])
    {
        if (strtolower($metodo) !== 'get' && preg_match('#/admin/[^/]+/[0-9]+#', $url)) {
            $datos = $datos + $this->versionDe($url);
        }

        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->{$metodo}($url, $datos);
    }

    /* ------------------------------ turnos ------------------------------ */

    public function test_lista_los_turnos_ordenados_por_hora(): void
    {
        $this->ajax('get', route('admin.turnos.index'))
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Mañana')
            ->assertJsonPath('data.2.nocturno', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_un_turno_no_puede_cruzarse_con_otro(): void
    {
        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Extra', 'time_start' => '13:00', 'time_end' => '15:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('time_start');

        // Los turnos nocturnos ocupan también la madrugada.
        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Madrugada', 'time_start' => '04:00', 'time_end' => '06:00'])
            ->assertStatus(422);
    }

    public function test_crea_edita_y_elimina_un_turno_libre(): void
    {
        Turno::where('name', 'Tarde')->update(['time_end' => '18:00']);   // libera 18:00 - 22:00

        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Refuerzo', 'time_start' => '18:00', 'time_end' => '22:00', 'description' => 'Apoyo'])
            ->assertOk()->assertJsonPath('turno.nombre', 'Refuerzo');

        $turno = Turno::firstWhere('name', 'Refuerzo');
        $this->ajax('put', route('admin.turnos.update', $turno->id), ['name' => 'Refuerzo 2', 'time_start' => '18:00', 'time_end' => '21:00'])
            ->assertOk();
        $this->ajax('delete', route('admin.turnos.destroy', $turno->id))->assertOk();
        $this->assertDatabaseMissing('schedules', ['name' => 'Refuerzo 2']);
    }

    public function test_exige_horas_distintas_y_nombre_unico(): void
    {
        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Mañana', 'time_start' => '10:00', 'time_end' => '10:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'time_end']);
    }

    public function test_no_elimina_un_turno_con_asistencias(): void
    {
        $persona = $this->persona();
        $turno = Turno::firstWhere('name', 'Mañana');
        Asistencia::create([
            'user_id' => $persona->id, 'date' => today()->toDateString(), 'time' => '06:00:00', 'schedule_id' => $turno->id,
            'shift_date' => today()->toDateString(), 'type' => 'entrada', 'status' => 'presente', 'source' => 'admin',
        ]);

        $this->ajax('delete', route('admin.turnos.destroy', $turno->id))->assertStatus(422);
        $this->assertDatabaseHas('schedules', ['id' => $turno->id]);
    }

    /* ----------------------------- feriados ----------------------------- */

    public function test_calcula_semana_santa_y_el_calendario_del_anio(): void
    {
        $cargador = new CargadorFeriados;

        $this->assertSame('2026-04-05', $cargador->domingoDePascua(2026)->toDateString());
        $this->assertSame('2027-03-28', $cargador->domingoDePascua(2027)->toDateString());

        $calendario = $cargador->calendarioPropio(2026);
        $this->assertSame('Jueves Santo', $calendario['2026-04-02']);
        $this->assertSame('Viernes Santo', $calendario['2026-04-03']);
        $this->assertSame('Navidad', $calendario['2026-12-25']);
        $this->assertCount(16, $calendario);
    }

    public function test_cargar_feriados_usa_la_api_y_no_duplica(): void
    {
        $anio = today()->year;
        Http::fake(['date.nager.at/*' => Http::response([
            ['date' => "{$anio}-01-01", 'localName' => 'Año Nuevo', 'name' => "New Year's Day"],
            ['date' => "{$anio}-12-25", 'localName' => 'Navidad', 'name' => 'Christmas Day'],
        ])]);

        $this->ajax('post', route('admin.feriados.cargar'))
            ->assertOk()->assertJsonPath('creados', 2)->assertJsonPath('origen', 'api');

        $this->ajax('post', route('admin.feriados.cargar'))
            ->assertOk()->assertJsonPath('creados', 0)->assertJsonPath('existentes', 2);

        $this->assertSame(2, Feriado::count());
    }

    public function test_si_la_api_falla_usa_el_calendario_propio(): void
    {
        Http::fake(['date.nager.at/*' => Http::response('error', 500)]);

        $this->ajax('post', route('admin.feriados.cargar'))
            ->assertOk()->assertJsonPath('origen', 'local')->assertJsonPath('creados', 16);

        $this->assertTrue(Feriado::esFeriado(today()->year.'-12-25'));
    }

    public function test_la_carga_con_barra_de_progreso_consulta_y_guarda_por_lotes(): void
    {
        $anio = today()->year;
        Http::fake(['date.nager.at/*' => Http::response([
            ['date' => "{$anio}-01-01", 'localName' => 'Año Nuevo'],
            ['date' => "{$anio}-05-01", 'localName' => 'Día del Trabajo'],
        ])]);

        $consulta = $this->ajax('get', route('admin.feriados.consultar'))->assertOk()
            ->assertJsonPath('origen', 'api')->assertJsonCount(2, 'feriados');
        $this->assertStringContainsString('Nager.Date', $consulta->json('fuente'));
        $this->assertSame(0, Feriado::count());   // consultar no guarda nada

        $lote = $consulta->json('feriados');
        $this->ajax('post', route('admin.feriados.cargar'), ['feriados' => [$lote[0]]])->assertOk()->assertJsonPath('creados', 1);
        $this->ajax('post', route('admin.feriados.cargar'), ['feriados' => $lote])->assertOk()
            ->assertJsonPath('creados', 1)->assertJsonPath('existentes', 1);

        $this->ajax('post', route('admin.feriados.cargar'), ['feriados' => [['date' => 'ayer', 'description' => 'x']]])->assertStatus(422);
    }

    public function test_la_carga_respeta_los_feriados_editados(): void
    {
        $anio = today()->year;
        Feriado::create(['date' => "{$anio}-12-25", 'description' => 'Navidad (local)', 'status' => false]);
        Http::fake(['date.nager.at/*' => Http::response([['date' => "{$anio}-12-25", 'localName' => 'Navidad']])]);

        $this->ajax('post', route('admin.feriados.cargar'))->assertOk()->assertJsonPath('existentes', 1);

        $this->assertSame('Navidad (local)', Feriado::first()->description);
        $this->assertFalse(Feriado::esFeriado("{$anio}-12-25"));   // inactivo: no cuenta en las validaciones
    }

    public function test_crud_y_filtros_de_feriados(): void
    {
        $this->ajax('post', route('admin.feriados.store'), ['date' => '2026-11-05', 'description' => 'Día del Trabajador Municipal', 'status' => 1])
            ->assertOk();
        $this->ajax('post', route('admin.feriados.store'), ['date' => '2026-11-05', 'description' => 'Otro', 'status' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('date');
        $this->ajax('post', route('admin.feriados.store'), ['date' => '2026-12-09', 'description' => 'Batalla de Ayacucho', 'status' => 0])
            ->assertOk();

        $this->ajax('get', route('admin.feriados.index', ['estado' => 'inactivo']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.dia', 'miércoles');

        $this->ajax('get', route('admin.feriados.index', ['desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertJsonCount(1, 'data')->assertJsonPath('resumen.total', 2)->assertJsonPath('resumen.activos', 1);

        $id = Feriado::firstWhere('date', '2026-11-05')->id;
        $this->ajax('put', route('admin.feriados.update', $id), ['date' => '2026-11-05', 'description' => 'Editado', 'status' => 0])->assertOk();
        $this->ajax('delete', route('admin.feriados.destroy', $id))->assertOk();
        $this->assertDatabaseCount('holidays', 1);
    }

    /* ----------------------------- vacaciones ----------------------------- */

    private function solicitar(Personal $persona, int $dias, int $desdeHoy, array $extra = [])
    {
        return $this->ajax('post', route('admin.vacaciones.store'), $extra + [
            'user_id' => $persona->id, 'days' => $dias, 'start_date' => today()->addDays($desdeHoy)->toDateString(),
        ]);
    }

    public function test_la_solicitud_queda_pendiente_y_calcula_la_fecha_de_fin(): void
    {
        $persona = $this->persona();

        $this->solicitar($persona, 10, 5, ['notes' => 'Viaje'])->assertOk();

        $vacacion = Vacacion::first();
        $this->assertSame(Vacacion::PENDIENTE, $vacacion->status);
        $this->assertSame(today()->addDays(14)->toDateString(), $vacacion->end_date->toDateString());   // inicio + 10 - 1
        $this->assertSame(30, Vacacion::diasDisponibles($persona->id, today()->year + 0) + Vacacion::diasAprobados($persona->id, today()->year));
    }

    public function test_solo_nombrados_y_permanentes_pueden_solicitar(): void
    {
        $this->solicitar($this->persona(Contrato::NOMBRADO), 5, 3)->assertOk();
        $this->solicitar($this->persona(Contrato::PERMANENTE), 5, 3)->assertOk();
        $this->solicitar($this->persona(Contrato::TEMPORAL), 5, 3)->assertStatus(422)->assertJsonValidationErrors('user_id');

        $sinContrato = Personal::create([
            'dni' => '39999999', 'usertype_id' => TipoPersonal::ayudante()->id, 'name' => 'Sin', 'last_name' => 'Contrato',
            'birthdate' => '1990-01-01', 'email' => 'sin@prueba.test', 'address' => 'x', 'password' => 'secret123', 'status' => true,
        ]);
        $this->solicitar($sinContrato, 5, 3)->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_no_acepta_fechas_que_coincidan_con_pendientes_o_aprobadas(): void
    {
        $persona = $this->persona();
        $this->solicitar($persona, 10, 10)->assertOk();                          // pendiente: días 10 al 19

        $this->solicitar($persona, 3, 18)->assertStatus(422)->assertJsonValidationErrors('start_date');   // se cruza
        $this->solicitar($persona, 3, 7)->assertOk();                             // termina el día 9: justo antes

        Vacacion::first()->update(['status' => Vacacion::RECHAZADA]);            // rechazada: las fechas quedan libres
        $this->solicitar($persona, 3, 12)->assertOk();

        // Otra persona sí puede pedir las mismas fechas.
        $this->solicitar($this->persona(), 3, 12)->assertOk();
    }

    public function test_no_permite_fechas_pasadas_ni_mas_dias_que_el_cupo(): void
    {
        $persona = $this->persona();

        $this->solicitar($persona, 5, -2)->assertStatus(422)->assertJsonValidationErrors('start_date');
        $this->solicitar($persona, 31, 5)->assertStatus(422)->assertJsonValidationErrors('days');
    }

    public function test_aprobar_descuenta_los_dias_y_rechazar_no(): void
    {
        $persona = $this->persona();
        $this->solicitar($persona, 10, 5)->assertOk();
        $this->solicitar($persona, 4, 40)->assertOk();
        [$primera, $segunda] = Vacacion::orderBy('id')->get();
        $anio = $primera->start_date->year;

        $this->ajax('patch', route('admin.vacaciones.aprobar', $primera->id))->assertOk();
        $this->assertSame(Vacacion::APROBADA, $primera->fresh()->status);
        $this->assertSame(10, Vacacion::diasAprobados($persona->id, $anio));

        $this->ajax('patch', route('admin.vacaciones.rechazar', $segunda->id))->assertOk();
        $this->assertSame(Vacacion::RECHAZADA, $segunda->fresh()->status);
        $this->assertSame(10, Vacacion::diasAprobados($persona->id, $anio));   // la rechazada no descuenta

        // Ya resueltas: no se pueden volver a resolver, editar ni eliminar.
        $this->ajax('patch', route('admin.vacaciones.aprobar', $segunda->id))->assertStatus(422);
        $this->ajax('put', route('admin.vacaciones.update', $primera->id), ['days' => 3, 'start_date' => today()->addDays(5)->toDateString()])->assertStatus(422);
        $this->ajax('delete', route('admin.vacaciones.destroy', $primera->id))->assertStatus(422);
    }

    public function test_no_se_aprueba_si_no_alcanzan_los_dias(): void
    {
        $persona = $this->persona();
        // Misma persona con dos solicitudes pendientes que juntas superan el cupo de 30: la segunda no se puede aprobar.
        $this->solicitar($persona, 20, 5)->assertOk();
        $this->solicitar($persona, 15, 40)->assertOk();
        [$a, $b] = Vacacion::orderBy('id')->get();

        if ($a->start_date->year !== $b->start_date->year) {
            $this->markTestSkipped('Las solicitudes caen en años distintos: no comparten cupo.');
        }

        $this->ajax('patch', route('admin.vacaciones.aprobar', $a->id))->assertOk();
        $this->ajax('patch', route('admin.vacaciones.aprobar', $b->id))->assertStatus(422);
        $this->assertSame(Vacacion::PENDIENTE, $b->fresh()->status);
    }

    public function test_edita_y_elimina_solo_pendientes(): void
    {
        $persona = $this->persona();
        $this->solicitar($persona, 5, 10)->assertOk();
        $vacacion = Vacacion::first();

        $this->ajax('put', route('admin.vacaciones.update', $vacacion->id), [
            'days' => 8, 'start_date' => today()->addDays(10)->toDateString(), 'notes' => 'Cambio',
        ])->assertOk();   // no choca consigo misma

        $this->assertSame(8, $vacacion->fresh()->days);
        $this->assertSame(today()->addDays(17)->toDateString(), $vacacion->fresh()->end_date->toDateString());

        $this->ajax('delete', route('admin.vacaciones.destroy', $vacacion->id))->assertOk();
        $this->assertDatabaseCount('vacations', 0);
    }

    public function test_las_vacaciones_aprobadas_sacan_al_personal_de_las_programaciones(): void
    {
        $persona = $this->persona();
        $inicio = today()->addDays(10);

        $this->assertTrue(Personal::disponibles($inicio)->whereKey($persona->id)->exists());

        Vacacion::create([
            'user_id' => $persona->id, 'start_date' => $inicio->toDateString(), 'end_date' => $inicio->copy()->addDays(4)->toDateString(),
            'days' => 5, 'status' => Vacacion::APROBADA,
        ]);

        $this->assertFalse(Personal::disponibles($inicio)->whereKey($persona->id)->exists());
        $this->assertFalse(Personal::disponibles($inicio->copy()->addDays(4))->whereKey($persona->id)->exists());
        $this->assertTrue(Personal::disponibles($inicio->copy()->addDays(5))->whereKey($persona->id)->exists());
        $this->assertTrue(Personal::disponibles()->whereKey($persona->id)->exists());   // hoy sí está disponible
        $this->assertTrue(Vacacion::estaDeVacaciones($persona->id, $inicio));
    }

    public function test_las_vacaciones_pendientes_no_afectan_la_disponibilidad(): void
    {
        $persona = $this->persona();
        $this->solicitar($persona, 5, 0)->assertOk();   // pendiente desde hoy

        $this->assertTrue(Personal::disponibles()->whereKey($persona->id)->exists());
    }

    public function test_el_buscador_informa_quien_puede_pedir_vacaciones(): void
    {
        $con = $this->persona();
        $sin = $this->persona(Contrato::TEMPORAL);

        $datos = $this->ajax('get', route('admin.personal.buscar', ['contexto' => 'vacaciones', 'q' => $con->dni]))->assertOk()->json('data');
        $this->assertStringContainsString('30 día(s) disponibles', $datos[0]['insignia_elegido']['texto']);

        $datos = $this->ajax('get', route('admin.personal.buscar', ['contexto' => 'vacaciones', 'q' => $sin->dni]))->assertOk()->json('data');
        $this->assertSame('rojo', $datos[0]['insignia_elegido']['color']);
    }

    public function test_las_pantallas_cargan(): void
    {
        foreach (['admin.turnos.index', 'admin.feriados.index', 'admin.vacaciones.index', 'admin.vacaciones.create', 'admin.turnos.create', 'admin.feriados.create'] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }
    }
}
