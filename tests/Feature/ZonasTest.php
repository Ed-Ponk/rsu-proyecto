<?php

namespace Tests\Feature;

use App\Models\ColorVehiculo;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Zona;
use App\Services\ReglasZona;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Avance 2: zonas con perímetro (polígono de coordenadas) y su mapa. */
class ZonasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'name' => 'Zona Prueba',
            'department' => 'Lambayeque',
            'province' => 'Chiclayo',
            'district' => 'José Leonardo Ortiz',
            'description' => 'Sector de prueba',
            'average_waste' => '150.50',
            'status' => 1,
            'coordinates' => [
                ['latitude' => -6.7480, 'longitude' => -79.8450],
                ['latitude' => -6.7480, 'longitude' => -79.8300],
                ['latitude' => -6.7600, 'longitude' => -79.8300],
                ['latitude' => -6.7600, 'longitude' => -79.8450],
            ],
        ];
    }

    private function ajax(string $metodo, string $url, array $datos = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->{$metodo}($url, $datos);
    }

    public function test_registra_una_zona_con_su_perimetro_en_orden(): void
    {
        $this->ajax('post', route('admin.zonas.store'), $this->datos())
            ->assertOk()->assertJsonPath('zona.puntos', 4);

        $zona = Zona::with('coordenadas')->firstWhere('name', 'Zona Prueba');
        $this->assertSame([1, 2, 3, 4], $zona->coordenadas->pluck('position')->all());
        $this->assertSame([-6.748, -79.845], $zona->vertices()[0]);
        $this->assertSame('150.50', $zona->average_waste);
        $this->assertTrue($zona->status);
    }

    public function test_exige_al_menos_tres_coordenadas(): void
    {
        $dos = $this->datos(['coordinates' => array_slice($this->datos()['coordinates'], 0, 2)]);

        $this->ajax('post', route('admin.zonas.store'), $dos)->assertStatus(422)->assertJsonValidationErrors('coordinates');
        $this->ajax('post', route('admin.zonas.store'), $this->datos(['coordinates' => []]))->assertStatus(422)->assertJsonValidationErrors('coordinates');
        $this->assertDatabaseCount('zones', 0);
    }

    public function test_rechaza_coordenadas_fuera_de_rango_o_de_peru(): void
    {
        $fuera = $this->datos();
        $fuera['coordinates'][1] = ['latitude' => 95, 'longitude' => -79.83];
        $this->ajax('post', route('admin.zonas.store'), $fuera)->assertStatus(422)->assertJsonValidationErrors('coordinates.1.latitude');

        $enMadrid = $this->datos(['coordinates' => [
            ['latitude' => 40.41, 'longitude' => -3.70], ['latitude' => 40.42, 'longitude' => -3.70], ['latitude' => 40.42, 'longitude' => -3.69],
        ]]);
        $this->ajax('post', route('admin.zonas.store'), $enMadrid)->assertStatus(422)->assertJsonValidationErrors('coordinates');
    }

    public function test_rechaza_un_perimetro_que_se_cruza_o_esta_alineado(): void
    {
        $reglas = new ReglasZona;
        $punto = fn ($lat, $lng) => ['latitude' => $lat, 'longitude' => $lng];

        // Forma de "moño": los lados 1-2 y 3-4 se cruzan.
        $mono = [$punto(-6.75, -79.85), $punto(-6.76, -79.83), $punto(-6.75, -79.83), $punto(-6.76, -79.85)];
        $this->assertStringContainsString('se cruza', $reglas->revisarPerimetro($mono));

        $recta = [$punto(-6.75, -79.85), $punto(-6.76, -79.84), $punto(-6.77, -79.83)];
        $this->assertStringContainsString('alineadas', $reglas->revisarPerimetro($recta));

        $repetido = [$punto(-6.75, -79.85), $punto(-6.75, -79.85), $punto(-6.76, -79.83), $punto(-6.77, -79.84)];
        $this->assertStringContainsString('repetidas', $reglas->revisarPerimetro($repetido));

        $bien = [$punto(-6.75, -79.85), $punto(-6.75, -79.83), $punto(-6.77, -79.83), $punto(-6.77, -79.85)];
        $this->assertNull($reglas->revisarPerimetro($bien));

        $this->ajax('post', route('admin.zonas.store'), $this->datos(['coordinates' => $mono]))->assertStatus(422);
    }

    public function test_valida_la_cascada_departamento_provincia_distrito(): void
    {
        $this->ajax('post', route('admin.zonas.store'), $this->datos(['province' => 'Ferreñafe']))
            ->assertStatus(422)->assertJsonValidationErrors('district');
        $this->ajax('post', route('admin.zonas.store'), $this->datos(['department' => 'Lima']))
            ->assertStatus(422)->assertJsonValidationErrors('department');
    }

    public function test_el_nombre_de_la_zona_es_unico(): void
    {
        $this->ajax('post', route('admin.zonas.store'), $this->datos())->assertOk();
        $this->ajax('post', route('admin.zonas.store'), $this->datos())->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_al_editar_reemplaza_el_perimetro_y_conserva_el_nombre(): void
    {
        $this->ajax('post', route('admin.zonas.store'), $this->datos())->assertOk();
        $zona = Zona::first();

        $nuevo = $this->datos(['status' => 0, 'average_waste' => '', 'coordinates' => [
            ['latitude' => -6.75, 'longitude' => -79.85], ['latitude' => -6.75, 'longitude' => -79.84], ['latitude' => -6.76, 'longitude' => -79.84],
        ]]);

        $this->ajax('put', route('admin.zonas.update', $zona->id), $nuevo)->assertOk()->assertJsonPath('zona.puntos', 3);

        $this->assertDatabaseCount('zonecoords', 3);
        $this->assertFalse($zona->fresh()->status);
        $this->assertNull($zona->fresh()->average_waste);
    }

    public function test_eliminar_la_zona_borra_sus_coordenadas(): void
    {
        $this->ajax('post', route('admin.zonas.store'), $this->datos())->assertOk();

        $this->ajax('delete', route('admin.zonas.destroy', Zona::first()->id))->assertOk();

        $this->assertDatabaseCount('zones', 0);
        $this->assertDatabaseCount('zonecoords', 0);
    }

    public function test_el_detalle_incluye_vertices_y_area(): void
    {
        $this->seed(ZonaSeeder::class);
        $zona = Zona::firstWhere('name', 'Zona Centro');

        $this->ajax('get', route('admin.zonas.show', $zona->id))
            ->assertOk()
            ->assertJsonPath('puntos', 4)
            ->assertJsonPath('residuos', 320)
            ->assertJsonCount(4, 'vertices');

        // 0.01° de latitud x 0.015° de longitud en la latitud de Chiclayo ≈ 1.1 km x 1.65 km.
        $this->assertEqualsWithDelta(1.85, $zona->areaKm2(), 0.05);
    }

    public function test_el_mapa_filtra_por_ubicacion_y_resume(): void
    {
        $this->seed(ZonaSeeder::class);
        Zona::firstWhere('name', 'Zona Este')->update(['status' => false]);

        $this->ajax('get', route('admin.zonas.mapa'))
            ->assertOk()
            ->assertJsonPath('resumen.zonas', 5)
            ->assertJsonPath('resumen.activas', 4)
            ->assertJsonPath('resumen.puntos', 20);

        $this->ajax('get', route('admin.zonas.mapa', ['province' => 'Ferreñafe']))->assertJsonPath('resumen.zonas', 0);
        $this->ajax('get', route('admin.zonas.mapa', ['district' => 'José Leonardo Ortiz']))->assertJsonPath('resumen.zonas', 5);
    }

    public function test_una_zona_no_puede_invadir_a_otra(): void
    {
        $this->seed(ZonaSeeder::class);   // "Zona Centro": lat -6.7600..-6.7700, lng -79.8450..-79.8300

        // Mismo perímetro que Zona Centro → invasión total.
        $igual = $this->datos(['name' => 'Copia', 'coordinates' => [
            ['latitude' => -6.7600, 'longitude' => -79.8450], ['latitude' => -6.7600, 'longitude' => -79.8300],
            ['latitude' => -6.7700, 'longitude' => -79.8300], ['latitude' => -6.7700, 'longitude' => -79.8450],
        ]]);
        $this->ajax('post', route('admin.zonas.store'), $igual)
            ->assertStatus(422)->assertJsonValidationErrors('coordinates');
        $this->assertStringContainsString('Zona', $this->ajax('post', route('admin.zonas.store'), $igual)->json('errors.coordinates.0'));

        // Invade solo una parte (se mete 0.005° dentro de Zona Centro).
        $parcial = $this->datos(['name' => 'Parcial', 'coordinates' => [
            ['latitude' => -6.7650, 'longitude' => -79.8400], ['latitude' => -6.7650, 'longitude' => -79.8250],
            ['latitude' => -6.7750, 'longitude' => -79.8250], ['latitude' => -6.7750, 'longitude' => -79.8400],
        ]]);
        $this->ajax('post', route('admin.zonas.store'), $parcial)->assertStatus(422)->assertJsonValidationErrors('coordinates');

        // Totalmente rodeada por otra zona.
        $dentro = $this->datos(['name' => 'Dentro', 'coordinates' => [
            ['latitude' => -6.7630, 'longitude' => -79.8400], ['latitude' => -6.7630, 'longitude' => -79.8350],
            ['latitude' => -6.7670, 'longitude' => -79.8350], ['latitude' => -6.7670, 'longitude' => -79.8400],
        ]]);
        $this->ajax('post', route('admin.zonas.store'), $dentro)->assertStatus(422);

        $this->assertDatabaseMissing('zones', ['name' => 'Parcial']);
    }

    public function test_una_zona_vecina_puede_compartir_el_borde(): void
    {
        $this->seed(ZonaSeeder::class);

        // Pegada al sur de "Zona Sur" (que termina en -6.7800): comparte el borde sin entrar.
        $vecina = $this->datos(['name' => 'Zona Extremo Sur', 'coordinates' => [
            ['latitude' => -6.7800, 'longitude' => -79.8450], ['latitude' => -6.7800, 'longitude' => -79.8300],
            ['latitude' => -6.7900, 'longitude' => -79.8300], ['latitude' => -6.7900, 'longitude' => -79.8450],
        ]]);

        $this->ajax('post', route('admin.zonas.store'), $vecina)->assertOk();
    }

    public function test_al_editar_no_choca_consigo_misma_pero_si_con_las_demas(): void
    {
        $this->seed(ZonaSeeder::class);
        $centro = Zona::firstWhere('name', 'Zona Centro');

        // Mismos puntos que ya tiene: no es una invasión (se ignora a sí misma).
        $propios = $this->datos(['name' => 'Zona Centro', 'coordinates' => $centro->coordenadas->map(fn ($c) => [
            'latitude' => $c->latitude, 'longitude' => $c->longitude,
        ])->all()]);
        $this->ajax('put', route('admin.zonas.update', $centro->id), $propios)->assertOk();

        // Estirarla hacia el norte invade a "Zona Norte".
        $estirada = $this->datos(['name' => 'Zona Centro', 'coordinates' => [
            ['latitude' => -6.7550, 'longitude' => -79.8450], ['latitude' => -6.7550, 'longitude' => -79.8300],
            ['latitude' => -6.7700, 'longitude' => -79.8300], ['latitude' => -6.7700, 'longitude' => -79.8450],
        ]]);
        $this->ajax('put', route('admin.zonas.update', $centro->id), $estirada)
            ->assertStatus(422)->assertJsonValidationErrors('coordinates');
    }

    public function test_la_verificacion_en_vivo_avisa_de_invasiones(): void
    {
        $this->seed(ZonaSeeder::class);
        $centro = Zona::firstWhere('name', 'Zona Centro');

        $invade = ['coordinates' => [
            ['latitude' => -6.7650, 'longitude' => -79.8400], ['latitude' => -6.7650, 'longitude' => -79.8250],
            ['latitude' => -6.7750, 'longitude' => -79.8250],
        ]];

        $this->ajax('post', route('admin.zonas.verificar'), $invade)->assertOk()->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'invade'));
        $this->ajax('post', route('admin.zonas.verificar'), ['coordinates' => array_slice($invade['coordinates'], 0, 2)])->assertOk()->assertJsonPath('mensaje', null);

        $libre = ['coordinates' => [
            ['latitude' => -6.7900, 'longitude' => -79.8450], ['latitude' => -6.7900, 'longitude' => -79.8300], ['latitude' => -6.8000, 'longitude' => -79.8300],
        ]];
        $this->ajax('post', route('admin.zonas.verificar'), $libre + ['zona' => $centro->id])->assertOk()->assertJsonPath('mensaje', null);
    }

    public function test_los_buscadores_de_catalogos_reemplazan_a_los_combos(): void
    {
        $marca = Marca::create(['name' => 'Hino', 'description' => null]);
        Modelo::create(['brand_id' => $marca->id, 'name' => 'Dutro', 'code' => 'DUTRO-H']);
        Modelo::create(['brand_id' => Marca::create(['name' => 'Isuzu'])->id, 'name' => 'Elf', 'code' => 'ELF']);
        TipoVehiculo::create(['name' => 'Compactador']);
        ColorVehiculo::create(['name' => 'Verde Oliva', 'code' => '#556B2F']);
        TipoPersonalSeeder::class;
        $this->seed(TipoPersonalSeeder::class);

        $this->ajax('get', route('admin.tiposvehiculo.buscar', ['q' => 'compac']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.titulo', 'Compactador');
        $this->ajax('get', route('admin.colores.buscar', ['q' => 'oliva']))->assertJsonPath('data.0.color', '#556B2F');
        $this->ajax('get', route('admin.tipospersonal.buscar', ['q' => 'conduc']))->assertJsonPath('data.0.titulo', 'Conductor');

        // Los modelos dependen de la marca: sin marca no hay resultados, con marca solo los suyos.
        $this->ajax('get', route('admin.modelos.buscar'))->assertJsonCount(0, 'data');
        $this->ajax('get', route('admin.modelos.buscar', ['marca' => $marca->id]))->assertJsonCount(1, 'data')->assertJsonPath('data.0.titulo', 'Dutro');
        $this->ajax('get', route('admin.modelos.buscar', ['marca' => $marca->id, 'q' => 'elf']))->assertJsonCount(0, 'data');
    }

    public function test_las_pantallas_de_zonas_cargan_con_las_librerias_del_mapa(): void
    {
        $this->get(route('admin.zonas.index'))
            ->assertOk()
            ->assertSee('vendor/leaflet/leaflet.js', false)
            ->assertSee('vendor/leaflet-draw/leaflet.draw.js', false)
            ->assertSee('Ver mapa de zonas');

        $this->get(route('admin.zonas.create'))->assertOk()->assertSee('zonaMapa', false);

        $this->assertFileExists(public_path('vendor/leaflet/leaflet.js'));
        $this->assertFileExists(public_path('vendor/leaflet-draw/leaflet.draw.js'));
    }
}
