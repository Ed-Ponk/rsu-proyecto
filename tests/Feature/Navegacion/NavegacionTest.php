<?php

namespace Tests\Feature\Navegacion;

use App\Models\Marca;
use App\Models\Modelo;
use App\Models\TipoPersonal;
use App\Models\User;
use Database\Seeders\TipoPersonalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionTest extends TestCase
{
    use RefreshDatabase;

    private const MODULOS = [
        'colores', 'marcas', 'modelos', 'tiposvehiculo', 'vehiculos',
        'tipospersonal', 'personal', 'contratos', 'asistencias', 'vacaciones',
        'usuarios', 'bitacora', 'turnos', 'zonas', 'feriados',
    ];

    public function test_las_paginas_y_sus_datos_requieren_autenticacion(): void
    {
        foreach (self::MODULOS as $modulo) {
            $this->get(route("admin.{$modulo}.index"))->assertRedirect('/login');
            $this->getJson(route("admin.{$modulo}.datos"))->assertUnauthorized();
        }
    }

    public function test_cada_pagina_carga_datos_desde_una_url_independiente(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (self::MODULOS as $modulo) {
            $pagina = $this->get(route("admin.{$modulo}.index"))
                ->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->assertSee(route("admin.{$modulo}.datos"), false);

            $this->assertContains('X-Requested-With', $pagina->baseResponse->getVary());
            $this->assertContains('Accept', $pagina->baseResponse->getVary());
            $this->assertTrue($pagina->headers->hasCacheControlDirective('no-store'));

            // El endpoint devuelve JSON incluso sin cabeceras AJAX.
            $this->get(route("admin.{$modulo}.datos"))
                ->assertOk()->assertHeader('Content-Type', 'application/json')->assertJsonStructure(['data']);
        }
    }

    public function test_modelos_conserva_ajax_antiguo_y_las_visitas_posteriores_son_html(): void
    {
        $this->actingAs(User::factory()->create());
        $marca = Marca::create(['name' => 'Marca de prueba']);
        Modelo::create(['brand_id' => $marca->id, 'name' => 'Compactador', 'code' => 'COM-01']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route('admin.modelos.index'))
            ->assertOk()->assertJsonPath('data.0.nombre', 'Compactador');
        $this->flushHeaders();

        foreach (range(1, 3) as $visita) {
            $this->get(route('admin.modelos.index'))->assertOk()->assertViewIs('admin.modelos.index');
        }
    }

    public function test_modelos_valida_guarda_redirige_actualiza_y_elimina(): void
    {
        $this->actingAs(User::factory()->create());
        $marca = Marca::create(['name' => 'Marca de prueba']);
        $datos = ['brand_id' => $marca->id, 'name' => 'Compactador', 'code' => 'COM-01'];

        $this->postJson(route('admin.modelos.store'), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['brand_id', 'name', 'code']);
        $this->post(route('admin.modelos.store'), $datos)->assertRedirect(route('admin.modelos.index'));
        $modelo = Modelo::firstOrFail();
        $this->postJson(route('admin.modelos.store'), $datos)->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);
        $this->putJson(route('admin.modelos.update', $modelo), $this->versionDe(route('admin.modelos.update', $modelo)) + (['name' => 'Recolector'] + $datos))->assertOk();
        $this->assertDatabaseHas('brandmodels', ['id' => $modelo->id, 'name' => 'Recolector']);
        $this->deleteJson(route('admin.modelos.destroy', $modelo), $this->versionDe(route('admin.modelos.destroy', $modelo)))->assertOk();
        $this->deleteJson(route('admin.modelos.destroy', $modelo), $this->versionDe(route('admin.modelos.destroy', $modelo)))->assertNotFound();
    }

    public function test_el_endpoint_de_asistencias_conserva_paginacion_y_validacion(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson(route('admin.asistencias.datos', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()->assertJsonPath('draw', 1)->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
        $this->getJson(route('admin.asistencias.datos', ['length' => 101]))
            ->assertUnprocessable()->assertJsonValidationErrors('length');
        $this->getJson(route('admin.feriados.datos', ['desde' => '2026-12-01', 'hasta' => '2026-01-01']))
            ->assertUnprocessable();
    }

    public function test_desactivar_una_cuenta_corta_el_acceso_de_su_sesion_abierta(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->get(route('admin.modelos.index'))->assertOk();
        $usuario->forceFill(['status' => false])->save();

        $this->get(route('admin.modelos.index'))->assertForbidden();
        $this->getJson(route('admin.modelos.datos'))->assertForbidden();
        $this->postJson(route('admin.modelos.store'), [])->assertForbidden();
    }

    public function test_una_identidad_de_personal_no_puede_acceder_al_panel(): void
    {
        $this->seed(TipoPersonalSeeder::class);
        $personal = User::factory()->create(['usertype_id' => TipoPersonal::ayudante()->id]);
        $this->actingAs($personal)->get(route('admin.modelos.index'))->assertForbidden();
        $this->getJson(route('admin.modelos.datos'))->assertForbidden();
    }
}
