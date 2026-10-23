<?php

namespace Tests\Feature\Negocio;

use App\Models\Asistencia;
use App\Models\ColorVehiculo;
use App\Models\Contrato;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\User;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReglasDeNegocioTest extends TestCase
{
    use RefreshDatabase;

    private int $contadorDni = 20000000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    /* ---------------------------- ayudas ---------------------------- */

    private function crearPersona(array $cambios = []): Personal
    {
        $dni = (string) ++$this->contadorDni;

        return Personal::create($cambios + [
            'dni' => $dni,
            'usertype_id' => TipoPersonal::ayudante()->id,
            'name' => 'Persona',
            'last_name' => "Número {$dni}",
            'birthdate' => '1990-01-01',
            'email' => "persona{$dni}@prueba.test",
            'address' => 'Av. Principal 123, Chiclayo',
            'password' => 'secret123',
            'status' => true,
        ]);
    }

    private function crearContrato(Personal $persona, array $cambios = []): Contrato
    {
        return Contrato::create($cambios + [
            'user_id' => $persona->id,
            'type' => Contrato::PERMANENTE,
            'start_date' => today()->subMonths(3)->toDateString(),
            'salary' => 1500,
            'is_active' => true,
        ]);
    }

    private function datosVehiculo(array $cambios = []): array
    {
        $marca = Marca::firstOrCreate(['name' => 'Volvo']);
        $modelo = Modelo::firstOrCreate(['brand_id' => $marca->id, 'name' => 'FM'], ['code' => 'FM-VOL']);

        return $cambios + [
            'name' => 'Vehículo de prueba', 'code' => 'VEH-100', 'plate' => 'ABC-123', 'year' => 2022,
            'load_capacity' => 8.5, 'fuel_capacity' => 120, 'compact_capacity' => 6, 'occupant_capacity' => 3,
            'status' => true,
            'brand_id' => $marca->id,
            'model_id' => $modelo->id,
            'type_id' => TipoVehiculo::firstOrCreate(['name' => 'Compactador'])->id,
            'color_id' => ColorVehiculo::firstOrCreate(['name' => 'Blanco'], ['code' => '#FFFFFF'])->id,
        ];
    }

    /* ------------------------- tipos de personal ------------------------- */

    public function test_conductor_se_puede_renombrar_y_conserva_su_regla_de_licencia(): void
    {
        $conductor = TipoPersonal::conductor();

        $this->putJson(route('admin.tipospersonal.update', $conductor->id), $this->versionDe(route('admin.tipospersonal.update', $conductor->id)) + (['name' => 'Chofer']))->assertOk();

        // Se reconoce por su clave interna, no por el nombre: sigue siendo "el conductor".
        $this->assertSame('Chofer', TipoPersonal::conductor()->name);
    }

    public function test_un_tipo_de_personal_solo_se_elimina_si_no_tiene_personal_ni_es_del_sistema(): void
    {
        $this->crearPersona(['usertype_id' => TipoPersonal::ayudante()->id]);

        // Conductor y Ayudante son funciones predefinidas: no se eliminan (aunque no tengan personal).
        $this->deleteJson(route('admin.tipospersonal.destroy', TipoPersonal::ayudante()->id), $this->versionDe(route('admin.tipospersonal.destroy', TipoPersonal::ayudante()->id)))->assertStatus(422);
        $this->deleteJson(route('admin.tipospersonal.destroy', TipoPersonal::conductor()->id), $this->versionDe(route('admin.tipospersonal.destroy', TipoPersonal::conductor()->id)))->assertStatus(422);
        $this->assertNotNull(TipoPersonal::conductor());

        // Una función creada por el usuario sí se elimina cuando no tiene personal.
        $propio = TipoPersonal::create(['name' => 'Supervisor', 'description' => 'Prueba']);
        $this->deleteJson(route('admin.tipospersonal.destroy', $propio->id), $this->versionDe(route('admin.tipospersonal.destroy', $propio->id)))->assertOk();
        $this->assertNull(TipoPersonal::find($propio->id));
    }

    /* ------------------------------ contratos ------------------------------ */

    public function test_una_persona_no_puede_tener_dos_contratos_activos(): void
    {
        $persona = $this->crearPersona();
        $this->crearContrato($persona);

        $this->postJson(route('admin.contratos.store'), [
            'user_id' => $persona->id, 'type' => 'permanente', 'start_date' => today()->toDateString(),
            'salary' => 1800, 'is_active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_el_contrato_temporal_exige_fecha_de_termino(): void
    {
        $persona = $this->crearPersona();

        $this->postJson(route('admin.contratos.store'), [
            'user_id' => $persona->id, 'type' => 'temporal', 'start_date' => today()->toDateString(),
            'salary' => 1800, 'is_active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_entre_dos_temporales_deben_pasar_dos_meses(): void
    {
        $persona = $this->crearPersona();
        $termino = today()->subDays(10);

        $this->crearContrato($persona, [
            'type' => Contrato::TEMPORAL,
            'start_date' => $termino->copy()->subMonths(2)->toDateString(),
            'end_date' => $termino->toDateString(),
            'is_active' => false,
        ]);

        // Un día después de vencer: todavía no se puede.
        $this->postJson(route('admin.contratos.store'), [
            'user_id' => $persona->id, 'type' => 'temporal',
            'start_date' => $termino->copy()->addDay()->toDateString(),
            'end_date' => $termino->copy()->addMonths(3)->toDateString(),
            'salary' => 1800, 'is_active' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('start_date');

        // Justo a los dos meses: sí.
        $this->postJson(route('admin.contratos.store'), [
            'user_id' => $persona->id, 'type' => 'temporal',
            'start_date' => $termino->copy()->addMonths(2)->toDateString(),
            'end_date' => $termino->copy()->addMonths(5)->toDateString(),
            'salary' => 1800, 'is_active' => false,
        ])->assertOk();
    }

    public function test_los_contratos_vencidos_se_cierran_solos(): void
    {
        $persona = $this->crearPersona();
        $contrato = $this->crearContrato($persona, [
            'type' => Contrato::TEMPORAL,
            'start_date' => today()->subMonths(3)->toDateString(),
            'end_date' => today()->subDay()->toDateString(),
        ]);

        Contrato::cerrarVencidos();

        $this->assertFalse($contrato->fresh()->is_active);
        $this->assertFalse(Personal::disponibles()->whereKey($persona->id)->exists());
    }

    public function test_un_contrato_vigente_deja_a_la_persona_disponible_para_programar(): void
    {
        $persona = $this->crearPersona();
        $this->assertFalse(Personal::disponibles()->whereKey($persona->id)->exists());

        $this->crearContrato($persona);

        $this->assertTrue(Personal::disponibles()->whereKey($persona->id)->exists());
    }

    /* ------------------------------ asistencias ------------------------------ */

    public function test_la_asistencia_exige_contrato_vigente(): void
    {
        $persona = $this->crearPersona();

        $this->postJson(route('admin.asistencias.store'), [
            'user_id' => $persona->id, 'date' => today()->toDateString(), 'time' => '06:30', 'status' => 'presente',
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_las_marcaciones_alternan_entrada_y_salida_y_asignan_turno(): void
    {
        $persona = $this->crearPersona();
        $this->crearContrato($persona);
        $ayer = today()->subDay()->toDateString();

        foreach (['06:10', '10:00', '14:30', '15:00'] as $hora) {
            $this->postJson(route('admin.asistencias.store'), [
                'user_id' => $persona->id, 'date' => $ayer, 'time' => $hora, 'status' => 'presente',
            ])->assertOk();
        }

        $marcaciones = Asistencia::with('turno')->where('user_id', $persona->id)->orderBy('time')->get();

        $this->assertSame(['entrada', 'salida', 'entrada', 'salida'], $marcaciones->pluck('type')->all());
        $this->assertSame(['Mañana', 'Mañana', 'Tarde', 'Tarde'], $marcaciones->map(fn ($m) => $m->turno?->name)->all());
    }

    /** Registra una marcación manual "presente" para ayer a la hora indicada. */
    private function marcarAyer(Personal $persona, string $hora)
    {
        return $this->postJson(route('admin.asistencias.store'), [
            'user_id' => $persona->id, 'date' => today()->subDay()->toDateString(), 'time' => $hora, 'status' => 'presente',
        ]);
    }

    public function test_al_eliminar_la_entrada_su_turno_se_reordena_y_recalcula_la_tardanza(): void
    {
        $persona = $this->crearPersona();
        $this->crearContrato($persona);

        foreach (['06:05', '10:00'] as $hora) {
            $this->marcarAyer($persona, $hora)->assertOk();
        }

        $entrada = Asistencia::where('user_id', $persona->id)->orderBy('time')->first();
        $this->deleteJson(route('admin.asistencias.destroy', $entrada->id), $this->versionDe(route('admin.asistencias.destroy', $entrada->id)))->assertOk();

        // La salida que quedó sola pasa a ser la entrada del turno Mañana: 10:00 son 240 min de tardanza.
        $restante = Asistencia::where('user_id', $persona->id)->sole();
        $this->assertSame('entrada', $restante->type);
        $this->assertSame(240, $restante->late_minutes);
    }

    public function test_solo_una_entrada_y_una_salida_por_turno(): void
    {
        $persona = $this->crearPersona();
        $this->crearContrato($persona);

        $this->marcarAyer($persona, '14:05')->assertOk();
        $this->marcarAyer($persona, '18:00')->assertOk();
        $this->marcarAyer($persona, '18:30')->assertStatus(422)->assertJsonValidationErrors('user_id');

        $this->assertSame(2, Asistencia::where('user_id', $persona->id)->count());
    }

    public function test_tardanza_respeta_la_tolerancia_y_se_cuenta_desde_el_inicio_del_turno(): void
    {
        $persona = $this->crearPersona();
        $this->crearContrato($persona);
        config(['proyecto.asistencia.minutos_tolerancia' => 10]);

        $this->marcarAyer($persona, '06:08')->assertOk();   // Mañana, dentro de la tolerancia
        $this->marcarAyer($persona, '13:00')->assertOk();   // salida
        $this->marcarAyer($persona, '14:25')->assertOk();   // Tarde, 25 min tarde

        $entradas = Asistencia::where('user_id', $persona->id)->where('type', 'entrada')->orderBy('time')->pluck('late_minutes')->all();
        $this->assertSame([0, 25], $entradas);
    }

    public function test_llegar_temprano_cuenta_para_el_turno_que_empieza_y_salir_tarde_para_el_propio(): void
    {
        $persona = $this->crearPersona();
        $this->crearContrato($persona);

        $this->marcarAyer($persona, '13:45')->assertOk();   // 15 min antes de la Tarde
        $this->marcarAyer($persona, '22:15')->assertOk();   // horas extra: salida de la Tarde, no entrada de la Noche

        $marcaciones = Asistencia::with('turno')->where('user_id', $persona->id)->orderBy('time')->get();
        $this->assertSame(['entrada', 'salida'], $marcaciones->pluck('type')->all());
        $this->assertSame(['Tarde', 'Tarde'], $marcaciones->map(fn ($m) => $m->turno->name)->all());
        $this->assertSame(0, $marcaciones->first()->late_minutes);
    }

    public function test_marcacion_con_dni_registra_y_bloquea_credenciales_invalidas(): void
    {
        $persona = $this->crearPersona();
        $this->crearContrato($persona);

        $this->postJson(route('admin.asistencias.marcar'), ['dni' => $persona->dni, 'password' => 'incorrecta'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson(route('admin.asistencias.marcar'), ['dni' => $persona->dni, 'password' => 'secret123'])
            ->assertOk()->assertJsonPath('marcacion.tipo', 'entrada');

        // Doble clic: la segunda marcación inmediata se rechaza por la espera mínima.
        $this->postJson(route('admin.asistencias.marcar'), ['dni' => $persona->dni, 'password' => 'secret123'])
            ->assertStatus(422)->assertJsonValidationErrors('dni');
    }

    public function test_no_se_registra_asistencia_en_el_futuro(): void
    {
        $persona = $this->crearPersona();
        $this->crearContrato($persona);

        $this->postJson(route('admin.asistencias.store'), [
            'user_id' => $persona->id, 'date' => today()->addDay()->toDateString(), 'time' => '08:00', 'status' => 'presente',
        ])->assertStatus(422);
    }

    public function test_los_reemplazos_son_de_la_misma_funcion_y_ya_asistieron(): void
    {
        $ausente = $this->crearPersona();
        $reservaPresente = $this->crearPersona();
        $reservaSinMarcar = $this->crearPersona();
        $otraFuncion = $this->crearPersona(['usertype_id' => TipoPersonal::conductor()->id, 'license' => 'Q12345678']);

        foreach ([$ausente, $reservaPresente, $reservaSinMarcar, $otraFuncion] as $persona) {
            $this->crearContrato($persona);
        }

        foreach ([$reservaPresente, $otraFuncion] as $persona) {
            Asistencia::create([
                'user_id' => $persona->id, 'date' => today()->toDateString(), 'time' => '06:00:00',
                'type' => 'entrada', 'status' => 'presente', 'source' => 'admin',
            ]);
        }

        $this->assertSame([$reservaPresente->id], Personal::reemplazosDe($ausente)->pluck('id')->all());
    }

    /* ------------------------------- vehículos ------------------------------- */

    public function test_formatos_de_placa_y_placa_unica(): void
    {
        foreach (['ABC123', 'AB-1234', 'ABC-123'] as $n => $placa) {
            $this->postJson(route('admin.vehiculos.store'), $this->datosVehiculo(['code' => "V-{$n}", 'plate' => $placa]))->assertOk();
        }

        $this->postJson(route('admin.vehiculos.store'), $this->datosVehiculo(['code' => 'V-X', 'plate' => 'AB12']))
            ->assertStatus(422)->assertJsonValidationErrors('plate');

        // "abc-123" se normaliza a mayúsculas y choca con la placa ya registrada.
        $this->postJson(route('admin.vehiculos.store'), $this->datosVehiculo(['code' => 'V-Y', 'plate' => 'abc-123']))
            ->assertStatus(422)->assertJsonValidationErrors('plate');
    }

    public function test_codigo_unico_y_anio_valido(): void
    {
        $this->postJson(route('admin.vehiculos.store'), $this->datosVehiculo())->assertOk();

        $this->postJson(route('admin.vehiculos.store'), $this->datosVehiculo(['plate' => 'XYZ-999']))
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->postJson(route('admin.vehiculos.store'), $this->datosVehiculo(['code' => 'V-VIEJO', 'plate' => 'OLD-111', 'year' => 1950]))
            ->assertStatus(422)->assertJsonValidationErrors('year');
    }

    /* -------------------------------- personal -------------------------------- */

    public function test_el_conductor_necesita_licencia_y_la_persona_debe_ser_mayor_de_edad(): void
    {
        $datos = [
            'dni' => '45678912', 'usertype_id' => TipoPersonal::conductor()->id, 'name' => 'Luis', 'last_name' => 'Soto',
            'birthdate' => today()->subYears(16)->toDateString(), 'email' => 'luis@prueba.test',
            'address' => 'Calle Los Pinos 456, Chiclayo', 'password' => 'secret123', 'status' => true,
        ];

        $this->postJson(route('admin.personal.store'), $datos)
            ->assertStatus(422)->assertJsonValidationErrors(['license', 'birthdate']);

        $datos['birthdate'] = '1990-05-05';
        $datos['license'] = 'Q12345678';

        $this->postJson(route('admin.personal.store'), $datos)->assertOk();
        $this->assertDatabaseHas('users', ['dni' => '45678912']);
    }

    public function test_el_personal_no_puede_iniciar_sesion_en_el_panel(): void
    {
        $this->crearPersona(['email' => 'conductor@prueba.test']);
        auth()->logout();

        $this->post('/login', ['email' => 'conductor@prueba.test', 'password' => 'secret123'])->assertSessionHasErrors();
        $this->assertGuest();
    }
}
