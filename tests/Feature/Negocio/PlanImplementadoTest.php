<?php

namespace Tests\Feature\Negocio;

use App\Actions\Jetstream\DeleteUser;
use App\Models\Bitacora;
use App\Models\ColorVehiculo;
use App\Models\Contrato;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\Turno;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\Vehiculo;
use App\Rules\ImagenSegura;
use App\Services\CargadorFeriados;
use App\Services\ReglasContrato;
use App\Services\ReglasZona;
use App\Services\ServicioAsistencia;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanImplementadoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(13, 0));
        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    private function persona(): Personal
    {
        $persona = Personal::create(['name' => 'Ana', 'last_name' => 'Prueba', 'dni' => '71234567', 'email' => 'ana@test.local',
            'birthdate' => '1990-01-01', 'address' => 'Av Principal 123', 'password' => 'secret123', 'status' => true, 'usertype_id' => TipoPersonal::ayudante()->id]);
        Contrato::create(['user_id' => $persona->id, 'type' => 'permanente', 'start_date' => today()->subYear(), 'salary' => 1500, 'is_active' => true]);

        return $persona;
    }

    public function test_un_formulario_obsoleto_no_sobrescribe_el_turno(): void
    {
        $turno = Turno::first();
        $datos = ['version' => $turno->version, 'name' => 'Mañana nueva', 'time_start' => substr($turno->time_start, 0, 5), 'time_end' => substr($turno->time_end, 0, 5)];
        $this->putJson(route('admin.turnos.update', $turno), $datos)->assertOk();
        $this->putJson(route('admin.turnos.update', $turno), $datos + ['description' => 'Obsoleto'])->assertStatus(409);
        $this->assertNotSame('Obsoleto', $turno->fresh()->description);
    }

    public function test_edicion_requiere_version(): void
    {
        $this->putJson(route('admin.turnos.update', Turno::first()), [])->assertUnprocessable()->assertJsonValidationErrors('version');
    }

    public function test_una_resolucion_anterior_no_puede_borrarse_con_version_vieja(): void
    {
        $persona = $this->persona();
        $vacacion = Vacacion::create(['user_id' => $persona->id, 'days' => 2, 'start_date' => today()->addDay(), 'end_date' => today()->addDays(2), 'status' => 'pendiente']);
        $version = $vacacion->fresh()->version;
        $this->patchJson(route('admin.vacaciones.aprobar', $vacacion), ['version' => $version])->assertOk();
        $this->deleteJson(route('admin.vacaciones.destroy', $vacacion), ['version' => $version])->assertConflict();
        $this->assertSame('aprobada', $vacacion->fresh()->status);
    }

    public function test_la_espera_se_comprueba_en_el_servicio_incluso_sin_controlador(): void
    {
        $persona = $this->persona();
        $servicio = app(ServicioAsistencia::class);
        $servicio->registrar($persona, now(), origen: 'marcacion');
        try {
            $servicio->registrar($persona, now(), origen: 'marcacion');
            $this->fail('Aceptó doble marcación');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Espere', $error->getMessage());
        }
        $this->assertSame(1, $persona->asistencias()->count());
    }

    public function test_servicio_recarga_el_estado_de_la_persona(): void
    {
        $persona = $this->persona();
        Personal::whereKey($persona->id)->update(['status' => false]);
        $this->expectException(ValidationException::class);
        app(ServicioAsistencia::class)->registrar($persona, now());
    }

    public function test_aprobar_vacaciones_iniciadas_rechaza_asistencias_existentes(): void
    {
        $persona = $this->persona();
        app(ServicioAsistencia::class)->registrar($persona, now());
        $vacacion = Vacacion::create(['user_id' => $persona->id, 'days' => 2, 'start_date' => today(), 'end_date' => today()->addDay(), 'status' => 'pendiente']);
        $this->patchJson(route('admin.vacaciones.aprobar', $vacacion), ['version' => $vacacion->fresh()->version])->assertUnprocessable();
        $this->assertSame('pendiente', $vacacion->fresh()->status);
    }

    public function test_ultimo_activo_no_se_elimina_aunque_existan_inactivos(): void
    {
        User::factory()->create(['status' => false]);
        $this->expectException(ValidationException::class);
        app(DeleteUser::class)->delete($this->admin);
    }

    public function test_contratos_historicos_revisan_el_vecino_posterior(): void
    {
        $persona = $this->persona();
        Contrato::create(['user_id' => $persona->id, 'type' => 'temporal', 'start_date' => '2025-09-01', 'end_date' => '2025-10-01', 'salary' => 1000, 'is_active' => false]);
        $errores = app(ReglasContrato::class)->revisar(['user_id' => $persona->id, 'type' => 'temporal', 'start_date' => '2025-08-01', 'end_date' => '2025-09-15', 'is_active' => false]);
        $this->assertArrayHasKey('start_date', $errores);
    }

    public function test_bitacora_conserva_actor_eliminado_y_resultado_de_marcacion(): void
    {
        $otro = User::factory()->create();
        $persona = $this->persona();
        $marca = app(ServicioAsistencia::class)->registrar($persona, now());
        $this->assertTrue(Bitacora::where('model', 'Asistencia')->where('model_id', $marca->id)->get()->contains(fn ($b) => ($b->after['type'] ?? null) === 'entrada'));
        $nombre = $this->admin->name;
        app(DeleteUser::class)->delete($this->admin);
        $this->assertTrue(Bitacora::where('actor_name', $nombre)->exists());
        $this->assertTrue((bool) $otro->fresh()->status);
    }

    public function test_cierre_automatico_registra_evento_y_es_repetible(): void
    {
        $persona = $this->persona();
        $persona->contratos()->update(['is_active' => false]);
        $contrato = Contrato::create(['user_id' => $persona->id, 'type' => 'temporal', 'start_date' => today()->subMonths(4), 'end_date' => today()->subDay(), 'salary' => 1000, 'is_active' => true]);
        $this->assertSame(1, Contrato::cerrarVencidos());
        $this->assertSame(0, Contrato::cerrarVencidos());
        $this->assertTrue(Bitacora::where('model', 'Contrato')->where('model_id', $contrato->id)->where('action', 'modificado')->exists());
    }

    public function test_periodos_invertidos_se_rechazan(): void
    {
        $this->getJson(route('admin.asistencias.datos', ['desde' => '2026-02-02', 'hasta' => '2026-01-01']))->assertUnprocessable();
    }

    public function test_lotes_invalidos_no_guardan_ni_el_primer_feriado(): void
    {
        $this->expectException(ValidationException::class);
        try {
            app(CargadorFeriados::class)->guardar(['2026-01-01' => 'Año nuevo', '2026-02-01' => 'x']);
        } finally {
            $this->assertDatabaseCount('holidays', 0);
        }
    }

    public function test_perimetro_rechaza_repeticiones_contactos_y_exceso_de_vertices(): void
    {
        $reglas = app(ReglasZona::class);
        $puntos = [['latitude' => -6.7, 'longitude' => -79.8], ['latitude' => -6.7, 'longitude' => -79.79], ['latitude' => -6.71, 'longitude' => -79.8], ['latitude' => -6.7, 'longitude' => -79.8]];
        $this->assertNotNull($reglas->revisarPerimetro($puntos));
        $this->assertStringContainsString('200', $reglas->revisarPerimetro(array_fill(0, 201, $puntos[0])));
    }

    public function test_diagnostico_no_modifica_los_datos(): void
    {
        $this->persona();
        $antes = DB::table('contracts')->get()->toJson();
        $eventos = Bitacora::count();
        $this->artisan('rsu:verificar-integridad')->assertSuccessful();
        $this->assertSame($antes, DB::table('contracts')->get()->toJson());
        $this->assertSame($eventos, Bitacora::count());
    }

    public function test_salarios_no_admiten_precision_que_se_perderia(): void
    {
        $persona = $this->persona();
        $this->postJson(route('admin.contratos.store'), ['user_id' => $persona->id, 'type' => 'permanente',
            'start_date' => today()->toDateString(), 'salary' => '100.999', 'is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('salary');
    }

    public function test_tipos_incorrectos_se_rechazan_antes_de_normalizar(): void
    {
        $this->postJson(route('admin.personal.store'), ['dni' => ['71234567'], 'email' => ['correo']])
            ->assertUnprocessable()->assertJsonValidationErrors(['dni', 'email']);
        $this->postJson(route('admin.asistencias.marcar'), ['dni' => ['71234567'], 'password' => 'secret123'])
            ->assertUnprocessable()->assertJsonValidationErrors('dni');
    }

    public function test_lote_con_fecha_repetida_se_rechaza_sin_colapsar_datos(): void
    {
        $this->postJson(route('admin.feriados.cargar'), ['feriados' => [
            ['date' => '2026-01-01', 'description' => 'Primero'], ['date' => '2026-01-01', 'description' => 'Segundo'],
        ]])->assertUnprocessable();
        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_turno_usado_no_se_puede_eliminar_directamente_en_sql(): void
    {
        $marca = app(ServicioAsistencia::class)->registrar($this->persona(), now());
        $this->expectException(QueryException::class);
        DB::table('schedules')->where('id', $marca->schedule_id)->delete();
    }

    public function test_estado_del_contrato_es_explicito_y_reintento_obsoleto_no_invierte(): void
    {
        $contrato = $this->persona()->contratos()->first();
        $url = route('admin.contratos.estado', $contrato);
        $datos = ['version' => $contrato->version, 'is_active' => false];
        $this->patchJson($url, $datos)->assertOk();
        $this->patchJson($url, $datos)->assertConflict();
        $this->assertFalse($contrato->fresh()->is_active);
    }

    public function test_cuenta_inactiva_no_puede_editar_su_perfil_abierto(): void
    {
        $this->admin->forceFill(['status' => false])->save();
        $this->putJson('/user/profile-information', ['name' => 'Nombre nuevo', 'email' => $this->admin->email])->assertForbidden();
    }

    public function test_imagen_de_perfil_inexistente_no_cambia_la_galeria(): void
    {
        $marca = Marca::create(['name' => 'Marca']);
        $modelo = Modelo::create(['name' => 'Modelo', 'code' => 'M1', 'brand_id' => $marca->id]);
        $tipo = TipoVehiculo::create(['name' => 'Tipo']);
        $color = ColorVehiculo::create(['name' => 'Color', 'code' => '#123456']);
        $vehiculo = Vehiculo::create(['name' => 'Vehículo', 'code' => 'V1', 'plate' => 'AAA123', 'year' => 2024,
            'brand_id' => $marca->id, 'model_id' => $modelo->id, 'type_id' => $tipo->id, 'color_id' => $color->id]);
        $this->postJson(route('admin.vehiculos.imagenes.guardar', $vehiculo), ['version' => 1, 'profile' => 'existing:999999'])
            ->assertUnprocessable()->assertJsonValidationErrors('profile');
        $this->assertSame(0, $vehiculo->imagenes()->count());
        $this->assertSame(1, (int) $vehiculo->fresh()->version);
    }

    public function test_bitacora_pagina_en_servidor_y_conserva_formato_anterior(): void
    {
        $this->persona();
        $this->getJson(route('admin.bitacora.datos', ['draw' => 1, 'start' => 0, 'length' => 1]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonStructure(['recordsTotal', 'recordsFiltered', 'draw']);
        $this->getJson(route('admin.bitacora.datos'))->assertOk()->assertJsonStructure(['data', 'truncado']);
    }

    public function test_imagenes_corruptas_y_de_mas_de_25_megapixeles_se_rechazan(): void
    {
        $regla = new ImagenSegura;
        $valida = UploadedFile::fake()->image('valida.png', 10, 10);
        $errores = [];
        $rechazar = function ($mensaje) use (&$errores) {
            $errores[] = $mensaje;
        };
        $regla->validate('images.0', $valida, $rechazar);
        $this->assertSame([], $errores);
        // Cambiar solo la cabecera evita reservar una imagen de 30 millones de píxeles en la prueba.
        $contenido = file_get_contents($valida->getPathname());
        $enorme = UploadedFile::fake()->createWithContent('enorme.png', substr_replace($contenido, pack('NN', 6000, 5000), 16, 8));
        $regla->validate('images.0', $enorme, $rechazar);
        $this->assertStringContainsString('25 megapíxeles', $errores[0]);
        $errores = [];
        $corrupta = UploadedFile::fake()->createWithContent('corrupta.png', substr($contenido, 0, 32));
        $regla->validate('images.0', $corrupta, $rechazar);
        $this->assertNotEmpty($errores);
    }
}
