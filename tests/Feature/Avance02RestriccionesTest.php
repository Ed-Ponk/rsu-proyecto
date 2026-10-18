<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Feriado;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\Zona;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Revisión del Avance 02: restricciones de negocio de vacaciones, turnos, feriados y zonas. */
class Avance02RestriccionesTest extends TestCase
{
    use RefreshDatabase;

    private int $dni = 40000000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    private function persona(string $contrato = Contrato::PERMANENTE, array $cambios = []): Personal
    {
        $dni = (string) ++$this->dni;

        $persona = Personal::create($cambios + [
            'dni' => $dni, 'usertype_id' => TipoPersonal::ayudante()->id, 'name' => 'Persona', 'last_name' => "N{$dni}",
            'birthdate' => '1990-01-01', 'email' => "r{$dni}@prueba.test", 'address' => 'Av. Principal 123',
            'password' => 'secret123', 'status' => true,
        ]);

        Contrato::create([
            'user_id' => $persona->id, 'type' => $contrato, 'start_date' => today()->subYear()->toDateString(),
            'end_date' => $contrato === Contrato::TEMPORAL ? today()->addMonths(3)->toDateString() : null, 'salary' => 1500, 'is_active' => true,
        ]);

        return $persona;
    }

    private function ajax(string $metodo, string $url, array $datos = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->{$metodo}($url, $datos);
    }

    private function vacacion(Personal $persona, int $desdeHoy, int $dias, string $estado = Vacacion::PENDIENTE): Vacacion
    {
        $inicio = today()->addDays($desdeHoy);

        return Vacacion::create([
            'user_id' => $persona->id, 'start_date' => $inicio->toDateString(),
            'end_date' => Vacacion::fechaFin($inicio, $dias)->toDateString(), 'days' => $dias, 'status' => $estado,
        ]);
    }

    /* ------------------------------- tardanza ------------------------------- */

    public function test_la_tardanza_se_expresa_en_horas_a_partir_de_los_60_minutos(): void
    {
        $this->assertSame('1 min', Asistencia::textoDeDuracion(1));
        $this->assertSame('59 min', Asistencia::textoDeDuracion(59));
        $this->assertSame('1 h', Asistencia::textoDeDuracion(60));
        $this->assertSame('1 h 1 min', Asistencia::textoDeDuracion(61));
        $this->assertSame('1 h 30 min', Asistencia::textoDeDuracion(90));
        $this->assertSame('2 h', Asistencia::textoDeDuracion(120));
        $this->assertSame('3 h 5 min', Asistencia::textoDeDuracion(185));
    }

    /* ------------------------------ vacaciones ------------------------------ */

    public function test_los_datos_de_la_solicitud_son_obligatorios_y_validos(): void
    {
        $persona = $this->persona();
        $inicio = today()->addDays(5)->toDateString();

        $this->ajax('post', route('admin.vacaciones.store'), [])->assertStatus(422)->assertJsonValidationErrors(['user_id', 'days', 'start_date']);
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => 99999, 'days' => 5, 'start_date' => $inicio])->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $persona->id, 'days' => 0, 'start_date' => $inicio])->assertStatus(422)->assertJsonValidationErrors('days');
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $persona->id, 'days' => 2.5, 'start_date' => $inicio])->assertStatus(422)->assertJsonValidationErrors('days');
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $persona->id, 'days' => 'abc', 'start_date' => $inicio])->assertStatus(422)->assertJsonValidationErrors('days');
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $persona->id, 'days' => 5, 'start_date' => 'no-es-fecha'])->assertStatus(422)->assertJsonValidationErrors('start_date');
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $persona->id, 'days' => 5, 'start_date' => $inicio, 'notes' => str_repeat('x', 501)])->assertStatus(422)->assertJsonValidationErrors('notes');
        $this->assertDatabaseCount('vacations', 0);
    }

    public function test_el_personal_inactivo_no_puede_solicitar(): void
    {
        $inactivo = $this->persona(Contrato::PERMANENTE, ['status' => false]);

        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $inactivo->id, 'days' => 5, 'start_date' => today()->addDays(3)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_no_se_aprueba_un_periodo_que_ya_termino(): void
    {
        $vacacion = $this->vacacion($this->persona(), -10, 5);   // terminó hace 6 días

        $this->ajax('patch', route('admin.vacaciones.aprobar', $vacacion->id))->assertStatus(422);
        $this->assertSame(Vacacion::PENDIENTE, $vacacion->fresh()->status);

        // Rechazarla o eliminarla sí se puede.
        $this->ajax('patch', route('admin.vacaciones.rechazar', $vacacion->id))->assertOk();
    }

    public function test_no_se_aprueba_si_la_persona_perdio_el_derecho(): void
    {
        $persona = $this->persona();
        $vacacion = $this->vacacion($persona, 10, 5);

        Contrato::where('user_id', $persona->id)->update(['is_active' => false]);   // contrato dado de baja
        $this->ajax('patch', route('admin.vacaciones.aprobar', $vacacion->id))->assertStatus(422);

        Contrato::where('user_id', $persona->id)->update(['is_active' => true]);
        $persona->update(['status' => false]);                                      // persona inactiva
        $this->ajax('patch', route('admin.vacaciones.aprobar', $vacacion->id))->assertStatus(422);

        $persona->update(['status' => true]);
        $this->ajax('patch', route('admin.vacaciones.aprobar', $vacacion->id))->assertOk();
    }

    public function test_al_editar_se_vuelven_a_aplicar_todas_las_reglas(): void
    {
        $persona = $this->persona();
        $primera = $this->vacacion($persona, 10, 5);        // días 10 al 14
        $segunda = $this->vacacion($persona, 30, 5);        // días 30 al 34

        // Moverla encima de la primera: choque.
        $this->ajax('put', route('admin.vacaciones.update', $segunda->id), ['days' => 5, 'start_date' => today()->addDays(12)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('start_date');
        // Pedir más días que el cupo.
        $this->ajax('put', route('admin.vacaciones.update', $segunda->id), ['days' => 31, 'start_date' => today()->addDays(30)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('days');
        // Mover a una fecha pasada.
        $this->ajax('put', route('admin.vacaciones.update', $segunda->id), ['days' => 5, 'start_date' => today()->subDays(3)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('start_date');
        // Un cambio válido sí.
        $this->ajax('put', route('admin.vacaciones.update', $segunda->id), ['days' => 3, 'start_date' => today()->addDays(40)->toDateString()])->assertOk();
        $this->assertSame(3, $segunda->fresh()->days);
        $this->assertSame($persona->id, $segunda->fresh()->user_id);   // la persona no cambia al editar
    }

    public function test_dos_personas_pueden_tener_las_mismas_fechas_y_las_rechazadas_las_liberan(): void
    {
        $a = $this->persona();
        $b = $this->persona(Contrato::NOMBRADO);

        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $a->id, 'days' => 5, 'start_date' => today()->addDays(10)->toDateString()])->assertOk();
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $b->id, 'days' => 5, 'start_date' => today()->addDays(10)->toDateString()])->assertOk();
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $a->id, 'days' => 5, 'start_date' => today()->addDays(10)->toDateString()])->assertStatus(422);

        Vacacion::where('user_id', $a->id)->first()->update(['status' => Vacacion::RECHAZADA]);
        $this->ajax('post', route('admin.vacaciones.store'), ['user_id' => $a->id, 'days' => 5, 'start_date' => today()->addDays(10)->toDateString()])->assertOk();
    }

    public function test_quien_esta_de_vacaciones_no_puede_registrar_asistencia(): void
    {
        $persona = $this->persona();
        $ayer = today()->subDay();
        $this->vacacion($persona, -1, 3, Vacacion::APROBADA);   // desde ayer, 3 días

        $this->ajax('post', route('admin.asistencias.store'), [
            'user_id' => $persona->id, 'date' => $ayer->toDateString(), 'time' => '07:00', 'status' => 'presente',
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');

        $this->ajax('post', route('admin.asistencias.store'), [
            'user_id' => $persona->id, 'date' => $ayer->toDateString(), 'time' => '07:00', 'status' => 'ausente',
        ])->assertStatus(422);

        $vista = $this->ajax('get', route('admin.asistencias.vistaprevia', ['user_id' => $persona->id, 'date' => $ayer->toDateString(), 'time' => '07:00']))->assertOk();
        $this->assertTrue($vista->json('persona.de_vacaciones'));
        $this->assertSame(0, Asistencia::count());
    }

    public function test_las_vacaciones_pendientes_o_rechazadas_no_impiden_la_asistencia(): void
    {
        $persona = $this->persona();
        $ayer = today()->subDay();
        $this->vacacion($persona, -1, 3, Vacacion::PENDIENTE);

        $this->ajax('post', route('admin.asistencias.store'), [
            'user_id' => $persona->id, 'date' => $ayer->toDateString(), 'time' => '06:05', 'status' => 'presente',
        ])->assertOk();
    }

    public function test_no_se_elimina_personal_con_vacaciones(): void
    {
        $persona = $this->persona();
        Contrato::where('user_id', $persona->id)->delete();
        $this->vacacion($persona, 10, 5);

        $this->ajax('delete', route('admin.personal.destroy', $persona->id))->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $persona->id]);
    }

    /* -------------------------------- turnos -------------------------------- */

    public function test_la_duracion_del_turno_tiene_limites(): void
    {
        \App\Models\Turno::query()->delete();   // sin otros turnos para probar solo la duración

        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Corto', 'time_start' => '08:00', 'time_end' => '08:30'])
            ->assertStatus(422)->assertJsonValidationErrors('time_end');
        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Largo', 'time_start' => '06:00', 'time_end' => '20:00'])
            ->assertStatus(422)->assertJsonValidationErrors('time_end');
        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Nocturno largo', 'time_start' => '20:00', 'time_end' => '09:00'])
            ->assertStatus(422);   // 13 horas

        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Normal', 'time_start' => '06:00', 'time_end' => '14:00'])->assertOk();
        $this->ajax('post', route('admin.turnos.store'), ['name' => 'Noche', 'time_start' => '22:00', 'time_end' => '06:00'])->assertOk();
    }

    public function test_el_turno_exige_horas_validas(): void
    {
        $this->ajax('post', route('admin.turnos.store'), ['name' => '', 'time_start' => '', 'time_end' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'time_start', 'time_end']);
        $this->ajax('post', route('admin.turnos.store'), ['name' => 'X', 'time_start' => '25:00', 'time_end' => '10:00'])
            ->assertStatus(422)->assertJsonValidationErrors('time_start');
        $this->ajax('post', route('admin.turnos.store'), ['name' => str_repeat('a', 51), 'time_start' => '08:00', 'time_end' => '09:00'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    /* ------------------------------- feriados ------------------------------- */

    public function test_el_feriado_valida_fecha_y_descripcion(): void
    {
        $this->ajax('post', route('admin.feriados.store'), ['date' => '', 'description' => '', 'status' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['date', 'description']);
        $this->ajax('post', route('admin.feriados.store'), ['date' => '2010-01-01', 'description' => 'Muy antiguo', 'status' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('date');
        $this->ajax('post', route('admin.feriados.store'), ['date' => (today()->year + 11).'-01-01', 'description' => 'Muy lejano', 'status' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('date');
        $this->ajax('post', route('admin.feriados.store'), ['date' => today()->year.'-05-01', 'description' => 'ab', 'status' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('description');
        $this->ajax('post', route('admin.feriados.store'), ['date' => today()->year.'-05-01', 'description' => 'Día del Trabajo', 'status' => 7])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_el_filtro_de_feriados_rechaza_un_rango_invertido(): void
    {
        $this->ajax('get', route('admin.feriados.index', ['desde' => '2026-12-01', 'hasta' => '2026-01-01']))
            ->assertStatus(422)->assertJsonValidationErrors('hasta');
        $this->ajax('get', route('admin.feriados.index', ['estado' => 'raro']))->assertStatus(422);
    }

    public function test_un_feriado_inactivo_no_afecta_y_uno_activo_si(): void
    {
        $fecha = today()->addDays(20)->toDateString();
        $feriado = Feriado::create(['date' => $fecha, 'description' => 'Prueba', 'status' => true]);
        $this->assertTrue(Feriado::esFeriado($fecha));

        $feriado->update(['status' => false]);
        $this->assertFalse(Feriado::esFeriado($fecha));
    }

    /* --------------------------------- zonas -------------------------------- */

    private function zona(array $coordenadas, array $cambios = []): array
    {
        return $cambios + [
            'name' => 'Zona X', 'department' => 'Lambayeque', 'province' => 'Chiclayo', 'district' => 'José Leonardo Ortiz',
            'status' => 1, 'coordinates' => array_map(fn ($p) => ['latitude' => $p[0], 'longitude' => $p[1]], $coordenadas),
        ];
    }

    public function test_las_coordenadas_deben_estar_dentro_del_departamento(): void
    {
        // Lima: dentro de Perú pero fuera de Lambayeque.
        $lima = $this->zona([[-12.04, -77.04], [-12.04, -77.03], [-12.05, -77.03], [-12.05, -77.04]]);

        $this->ajax('post', route('admin.zonas.store'), $lima)->assertStatus(422)->assertJsonValidationErrors('coordinates');
        $this->assertStringContainsString('fuera del departamento', $this->ajax('post', route('admin.zonas.store'), $lima)->json('errors.coordinates.0'));

        $this->ajax('post', route('admin.zonas.verificar'), ['department' => 'Lambayeque'] + ['coordinates' => $lima['coordinates']])
            ->assertOk()->assertJsonPath('mensaje', fn ($m) => str_contains((string) $m, 'fuera del departamento'));
    }

    public function test_la_superficie_de_la_zona_tiene_limites(): void
    {
        // ~5 m x 5 m
        $minima = $this->zona([[-6.7500, -79.8400], [-6.7500, -79.84005], [-6.75005, -79.84005], [-6.75005, -79.8400]]);
        $this->ajax('post', route('admin.zonas.store'), $minima)->assertStatus(422)->assertJsonValidationErrors('coordinates');

        // ~30 km x 30 km
        $enorme = $this->zona([[-6.60, -79.95], [-6.60, -79.68], [-6.87, -79.68], [-6.87, -79.95]]);
        $this->assertStringContainsString('demasiado grande', $this->ajax('post', route('admin.zonas.store'), $enorme)->json('errors.coordinates.0'));

        $normal = $this->zona([[-6.7480, -79.8450], [-6.7480, -79.8300], [-6.7600, -79.8300], [-6.7600, -79.8450]]);
        $this->ajax('post', route('admin.zonas.store'), $normal)->assertOk();
    }

    public function test_los_datos_de_la_zona_se_validan(): void
    {
        $base = [[-6.7480, -79.8450], [-6.7480, -79.8300], [-6.7600, -79.8300]];

        $this->ajax('post', route('admin.zonas.store'), $this->zona($base, ['name' => '']))->assertStatus(422)->assertJsonValidationErrors('name');
        $this->ajax('post', route('admin.zonas.store'), $this->zona($base, ['average_waste' => -5]))->assertStatus(422)->assertJsonValidationErrors('average_waste');
        $this->ajax('post', route('admin.zonas.store'), $this->zona($base, ['average_waste' => 'mucho']))->assertStatus(422)->assertJsonValidationErrors('average_waste');
        $this->ajax('post', route('admin.zonas.store'), $this->zona($base, ['status' => 5]))->assertStatus(422)->assertJsonValidationErrors('status');
        $this->ajax('post', route('admin.zonas.store'), $this->zona($base, ['description' => str_repeat('x', 501)]))->assertStatus(422)->assertJsonValidationErrors('description');
        $this->ajax('post', route('admin.zonas.store'), $this->zona($base, ['name' => str_repeat('z', 101)]))->assertStatus(422)->assertJsonValidationErrors('name');

        $letras = $this->zona($base);
        $letras['coordinates'][0]['latitude'] = 'abc';
        $this->ajax('post', route('admin.zonas.store'), $letras)->assertStatus(422)->assertJsonValidationErrors('coordinates.0.latitude');
        $this->assertDatabaseCount('zones', 0);
    }

    public function test_las_zonas_inactivas_tambien_protegen_su_territorio(): void
    {
        $this->seed(ZonaSeeder::class);
        Zona::query()->update(['status' => false]);

        $igual = $this->zona([[-6.7600, -79.8450], [-6.7600, -79.8300], [-6.7700, -79.8300], [-6.7700, -79.8450]], ['name' => 'Otra']);
        $this->ajax('post', route('admin.zonas.store'), $igual)->assertStatus(422);
    }
}
