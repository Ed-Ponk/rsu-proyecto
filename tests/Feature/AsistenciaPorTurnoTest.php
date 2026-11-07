<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Services\ReglasProgramacion;
use App\Services\ServicioCambios;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La asistencia se mide por TURNO: una programación solo está completa cuando todo su personal marcó en su turno,
 * la marcación de otro turno no cuenta, y quien cubre a alguien debe estar presente en ese mismo turno.
 */
class AsistenciaPorTurnoTest extends TestCase
{
    use RefreshDatabase;

    private Turno $manana;

    private Turno $tarde;

    private Turno $noche;

    private int $dni = 71000000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class, ZonaSeeder::class, MarcaSeeder::class, ModeloSeeder::class,
            TipoVehiculoSeeder::class, ColorVehiculoSeeder::class, VehiculoSeeder::class]);
        $this->actingAs(User::factory()->create());

        // Hoy a las 10:00: el turno Mañana (06:00-14:00) ya empezó; Tarde y Noche todavía no.
        $this->travelTo(Carbon::parse('2026-11-10 10:00:00'));

        $this->manana = Turno::firstWhere('name', 'Mañana');
        $this->tarde = Turno::firstWhere('name', 'Tarde');
        $this->noche = Turno::firstWhere('name', 'Noche');
    }

    private function persona(string $funcion, string $nombre): Personal
    {
        $tipo = $funcion === 'conductor' ? TipoPersonal::conductor() : TipoPersonal::ayudante();
        $this->dni++;

        $persona = Personal::create([
            'dni' => (string) $this->dni, 'usertype_id' => $tipo->id, 'name' => $nombre, 'last_name' => 'Prueba Turno',
            'birthdate' => '1990-01-01', 'email' => "p{$this->dni}@prueba.test", 'address' => 'Av. Principal 123, Chiclayo',
            'password' => 'secret123', 'status' => true,
        ]);
        Contrato::create(['user_id' => $persona->id, 'type' => Contrato::PERMANENTE, 'start_date' => today()->subYear()->toDateString(), 'salary' => 1500, 'is_active' => true]);

        return $persona;
    }

    private function programacion(Turno $turno, Personal $conductor, array $ayudantes, int $vehiculo = 0, int $zona = 0): Programacion
    {
        $programacion = Programacion::create([
            'date' => today()->toDateString(), 'zone_id' => Zona::orderBy('id')->skip($zona)->value('id'), 'shift_id' => $turno->id,
            'vehicle_id' => Vehiculo::orderBy('id')->skip($vehiculo)->value('id'), 'driver_id' => $conductor->id, 'status' => Programacion::PROGRAMADO,
        ]);
        $programacion->asignarAyudantes(array_map(fn (Personal $p) => $p->id, $ayudantes));

        return $programacion->fresh();
    }

    private function marcar(Personal $persona, Turno $turno): void
    {
        Asistencia::create([
            'user_id' => $persona->id, 'date' => today()->toDateString(), 'time' => substr($turno->time_start, 0, 5).':00',
            'schedule_id' => $turno->id, 'shift_date' => today()->toDateString(), 'type' => Asistencia::ENTRADA,
            'status' => Asistencia::PRESENTE, 'source' => 'admin',
        ]);
    }

    private function dashboard(): array
    {
        return $this->getJson(route('admin.programaciones.dashboard', ['fecha' => today()->toDateString()]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json();
    }

    public function test_la_asistencia_de_otro_turno_no_cuenta_en_el_dashboard(): void
    {
        $conductor = $this->persona('conductor', 'Carlos');
        $ayudante = $this->persona('ayudante', 'Ana');
        $this->programacion($this->manana, $conductor, [$ayudante]);

        $this->marcar($conductor, $this->noche);   // marcó, pero en el turno Noche
        $this->marcar($ayudante, $this->manana);

        $tarjeta = $this->dashboard()['programaciones'][0];

        $this->assertFalse($tarjeta['completa']);
        $this->assertSame(1, $tarjeta['presentes']);
        $this->assertSame(1, $tarjeta['faltantes']);
    }

    public function test_solo_esta_completa_cuando_todos_marcaron_en_su_turno(): void
    {
        $conductor = $this->persona('conductor', 'Carlos');
        $ayudante = $this->persona('ayudante', 'Ana');
        $this->programacion($this->manana, $conductor, [$ayudante]);

        $this->marcar($conductor, $this->manana);
        $this->marcar($ayudante, $this->manana);

        $json = $this->dashboard();

        $this->assertTrue($json['programaciones'][0]['completa']);
        $this->assertSame(['total' => 1, 'completas' => 1, 'incompletas' => 0, 'faltantes' => 0], $json['kpis']);
    }

    public function test_antes_de_empezar_el_turno_queda_incompleta_con_personal_pendiente(): void
    {
        $this->programacion($this->tarde, $this->persona('conductor', 'Carlos'), [$this->persona('ayudante', 'Ana')]);

        $tarjeta = $this->dashboard()['programaciones'][0];

        $this->assertFalse($tarjeta['completa']);
        $this->assertSame(0, $tarjeta['presentes']);
        $this->assertSame(0, $tarjeta['faltantes']);
        $this->assertSame(2, $tarjeta['pendientes']);
    }

    public function test_quien_cubre_debe_tener_asistencia_en_el_mismo_turno(): void
    {
        $conductor = $this->persona('conductor', 'Carlos');
        $titular = $this->persona('ayudante', 'Ana');
        $reemplazo = $this->persona('ayudante', 'Luis');
        $programacion = $this->programacion($this->manana, $conductor, [$titular]);
        $this->marcar($conductor, $this->manana);
        $this->marcar($reemplazo, $this->noche);   // presente, pero en otro turno

        $datos = ['vehiculo_id' => $programacion->vehicle_id, 'conductor_id' => $conductor->id, 'ayudantes' => [$reemplazo->id],
            'motivos' => ['ayudante_1' => ['descripcion' => 'El titular no vino']]];

        $candidato = collect(app(ReglasProgramacion::class)->candidatos($programacion, 'ayudante', $titular->id))
            ->firstWhere('persona.id', $reemplazo->id);
        $this->assertFalse($candidato['disponible']);
        $this->assertStringContainsString('turno Mañana', $candidato['motivo']);

        try {
            app(ServicioCambios::class)->modificar($programacion, $datos);
            $this->fail('Debió rechazar a quien no marcó en el turno Mañana.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('turno Mañana', collect($e->errors())->flatten()->implode(' '));
        }

        $this->marcar($reemplazo, $this->manana);
        $this->assertSame(1, app(ServicioCambios::class)->modificar($programacion->fresh(), $datos));
        $this->assertSame([$reemplazo->id], $programacion->fresh()->ayudantes->pluck('id')->all());
    }

    public function test_no_cubre_quien_ya_esta_programado_en_otra_zona_en_ese_turno(): void
    {
        $titular = $this->persona('ayudante', 'Ana');
        $ocupado = $this->persona('ayudante', 'Luis');
        $programacion = $this->programacion($this->manana, $this->persona('conductor', 'Carlos'), [$titular]);
        $this->programacion($this->manana, $this->persona('conductor', 'Jorge'), [$ocupado], vehiculo: 1, zona: 1);
        $this->marcar($ocupado, $this->manana);

        $candidato = collect(app(ReglasProgramacion::class)->candidatos($programacion, 'ayudante', $titular->id))
            ->firstWhere('persona.id', $ocupado->id);

        $this->assertFalse($candidato['disponible']);
    }

    public function test_no_se_cambia_el_turno_si_alguien_ya_marco_en_el_actual(): void
    {
        $conductor = $this->persona('conductor', 'Carlos');
        $ayudante = $this->persona('ayudante', 'Ana');
        $programacion = $this->programacion($this->manana, $conductor, [$ayudante]);
        $this->marcar($ayudante, $this->manana);

        $this->expectException(ValidationException::class);
        app(ServicioCambios::class)->modificar($programacion, [
            'turno_id' => $this->tarde->id, 'vehiculo_id' => $programacion->vehicle_id, 'conductor_id' => $conductor->id, 'ayudantes' => [$ayudante->id],
            'motivos' => ['turno' => ['descripcion' => 'Prueba']],
        ]);
    }

    public function test_el_cambio_masivo_exige_asistencia_del_que_entra_en_el_mismo_turno(): void
    {
        $titular = $this->persona('ayudante', 'Ana');
        $reemplazo = $this->persona('ayudante', 'Luis');
        $this->programacion($this->manana, $this->persona('conductor', 'Carlos'), [$titular]);
        $this->marcar($reemplazo, $this->tarde);

        $revision = app(ReglasProgramacion::class)->revisarReemplazo([
            'rol' => 'ayudante', 'saliente_id' => $titular->id, 'entrante_id' => $reemplazo->id,
            'desde' => today()->toDateString(), 'hasta' => today()->toDateString(),
        ]);

        $this->assertFalse($revision['ok']);
        $this->assertStringContainsString('turno Mañana', implode(' ', $revision['errores']));
    }

    public function test_el_historial_muestra_el_turno_con_palabras(): void
    {
        $programacion = $this->programacion($this->manana, $this->persona('conductor', 'Carlos'), [$this->persona('ayudante', 'Ana')]);
        $programacion->moverATurno($this->tarde->id);

        $this->get(route('admin.programaciones.historial', $programacion->id))
            ->assertOk()
            ->assertSee('Turno: Mañana (06:00 - 14:00) → Tarde (14:00 - 22:00)')
            ->assertDontSee('shift_id');
    }

    public function test_la_bitacora_general_se_lee_sin_nombres_tecnicos(): void
    {
        $conductor = $this->persona('conductor', 'Carlos');
        $programacion = $this->programacion($this->manana, $conductor, [$this->persona('ayudante', 'Ana')]);
        $programacion->moverATurno($this->tarde->id);
        $this->marcar($conductor, $this->tarde);

        $filas = collect($this->getJson(route('admin.bitacora.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data'));
        $todo = $filas->map(fn (array $f) => $f['registro'].' | '.$f['detalle'])->implode("\n");

        $this->assertStringContainsString('Turno: Mañana (06:00 - 14:00) → Tarde (14:00 - 22:00)', $todo);
        $this->assertStringContainsString('Programación: 10/11/2026', $todo);
        $this->assertStringContainsString('Asistencia: Carlos Prueba Turno (10/11/2026)', $todo);
        $this->assertStringContainsString('Personal: Carlos Prueba Turno', $todo);
        foreach (['shift_id', 'schedule_id', 'user_id', 'driver_id', 'Programacion #'] as $tecnico) {
            $this->assertStringNotContainsString($tecnico, $todo);
        }
    }
}
