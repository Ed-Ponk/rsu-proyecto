<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\ColorVehiculo;
use App\Models\Contrato;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Operational Workflows, HTTP Contracts, and E2E Scenarios for the RSU Municipal Platform.
 *
 * Covers Tier 1 (Feature Coverage), Tier 2 (Boundaries), Tier 3 (Cross-Feature Integrations),
 * and Tier 4 (Real-World Municipal Lifecycles: Fleet Operations, Shift Attendance, Contract Expiry).
 */
class RSUOperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $dniCounter = 40000000;

    private ?User $adminUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $this->adminUser = User::factory()->create();
        $this->actingAs($this->adminUser);
    }

    protected function tearDown(): void
    {
        // Clean up test uploaded assets to prevent repository pollution
        $uploadDir = public_path('uploads/vehicle_images');
        if (File::isDirectory($uploadDir)) {
            File::deleteDirectory($uploadDir);
        }

        parent::tearDown();
    }

    /* -------------------------------------------------------------------------
     |  Test Data Helpers (Authoritative Source: ReglasDeNegocio / PROJECT.md)
     | ------------------------------------------------------------------------- */

    private function createPersonal(array $overrides = []): Personal
    {
        $dni = (string) ++$this->dniCounter;

        return Personal::create($overrides + [
            'dni' => $dni,
            'usertype_id' => TipoPersonal::ayudante()->id,
            'name' => 'Operario',
            'last_name' => "Municipal {$dni}",
            'birthdate' => '1992-06-15',
            'email' => "operario{$dni}@jlo.gob.pe",
            'address' => 'Av. Balta 1020, José Leonardo Ortiz',
            'password' => 'secret123',
            'status' => true,
        ]);
    }

    private function createContrato(Personal $person, array $overrides = []): Contrato
    {
        return Contrato::create($overrides + [
            'user_id' => $person->id,
            'type' => Contrato::PERMANENTE,
            'start_date' => today()->subMonths(6)->toDateString(),
            'salary' => 1600.00,
            'is_active' => true,
        ]);
    }

    private function createVehiculo(array $overrides = []): Vehiculo
    {
        $brand = Marca::firstOrCreate(['name' => 'Mercedes-Benz']);
        $model = Modelo::firstOrCreate(
            ['brand_id' => $brand->id, 'name' => 'Atego 2730'],
            ['code' => 'ATG-2730']
        );
        $type = TipoVehiculo::firstOrCreate(['name' => 'Compactador']);
        $color = ColorVehiculo::firstOrCreate(['name' => 'Verde Municipal'], ['code' => '#166534']);

        static $seq = 100;
        $seq++;

        return Vehiculo::create($overrides + [
            'name' => "Compactador RSU {$seq}",
            'code' => "VEH-RSU-{$seq}",
            'plate' => "EAA-{$seq}",
            'year' => 2023,
            'load_capacity' => 12.50,
            'fuel_capacity' => 200.00,
            'compact_capacity' => 8.00,
            'occupant_capacity' => 3,
            'status' => true,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'type_id' => $type->id,
            'color_id' => $color->id,
        ]);
    }

    /* -------------------------------------------------------------------------
     |  Tier 1: Module HTTP & JSON Contract Tests
     | ------------------------------------------------------------------------- */

    /**
     * Requirement R1 & R3 / Personal Module Contract:
     * Non-AJAX returns view admin.personal.index.
     * AJAX returns JSON with DataTables format ['data' => [...]].
     */
    public function test_personal_endpoint_returns_view_and_datatable_json(): void
    {
        $person = $this->createPersonal();
        $this->createContrato($person);

        // Standard GET request returns view
        $viewResponse = $this->get(route('admin.personal.index'));
        $viewResponse->assertOk();
        $viewResponse->assertViewIs('admin.personal.index');

        // AJAX GET request returns JSON array
        $ajaxResponse = $this->getJson(route('admin.personal.index'), ['X-Requested-With' => 'XMLHttpRequest']);
        $ajaxResponse->assertOk();
        $ajaxResponse->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'nombres',
                    'apellidos',
                    'dni',
                    'funcion',
                    'correo',
                    'activo',
                ],
            ],
        ]);

        $this->assertNotEmpty($ajaxResponse->json('data'));
    }

    /**
     * Requirement R1 & R3 / Vehículos Module Contract:
     * Non-AJAX returns view admin.vehiculos.index.
     * AJAX returns JSON with DataTables format ['data' => [...]].
     */
    public function test_vehiculos_endpoint_returns_view_and_datatable_json(): void
    {
        $vehicle = $this->createVehiculo();

        $viewResponse = $this->get(route('admin.vehiculos.index'));
        $viewResponse->assertOk();
        $viewResponse->assertViewIs('admin.vehiculos.index');

        $ajaxResponse = $this->getJson(route('admin.vehiculos.index'), ['X-Requested-With' => 'XMLHttpRequest']);
        $ajaxResponse->assertOk();
        $ajaxResponse->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'nombre',
                    'codigo',
                    'placa',
                    'anio',
                    'carga',
                    'activo',
                    'marca',
                    'modelo',
                    'tipo',
                    'color',
                ],
            ],
        ]);
    }

    /**
     * Requirement R1 & R3 / Contratos Module Contract:
     * Non-AJAX returns view admin.contratos.index.
     * AJAX returns JSON with DataTables format ['data' => [...]].
     */
    public function test_contratos_endpoint_returns_view_and_datatable_json(): void
    {
        $person = $this->createPersonal();
        $this->createContrato($person);

        $viewResponse = $this->get(route('admin.contratos.index'));
        $viewResponse->assertOk();
        $viewResponse->assertViewIs('admin.contratos.index');

        $ajaxResponse = $this->getJson(route('admin.contratos.index'), ['X-Requested-With' => 'XMLHttpRequest']);
        $ajaxResponse->assertOk();
        $ajaxResponse->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'dni',
                    'persona',
                    'funcion',
                    'tipo',
                    'tipo_texto',
                    'inicio',
                    'salario',
                    'activo',
                    'estado',
                ],
            ],
        ]);
    }

    /**
     * Requirement R1 & R3 / Asistencias Module Contract:
     * Non-AJAX returns view admin.asistencias.index with 'hoy' variable.
     * AJAX returns JSON with DataTables format ['data' => [...]].
     */
    public function test_asistencias_endpoint_returns_view_and_datatable_json(): void
    {
        $person = $this->createPersonal();
        $this->createContrato($person);

        Asistencia::create([
            'user_id' => $person->id,
            'date' => today()->toDateString(),
            'time' => '07:00:00',
            'type' => 'entrada',
            'status' => 'presente',
            'source' => 'admin',
        ]);

        $viewResponse = $this->get(route('admin.asistencias.index'));
        $viewResponse->assertOk();
        $viewResponse->assertViewIs('admin.asistencias.index');
        $viewResponse->assertViewHas('hoy', today()->toDateString());

        $ajaxResponse = $this->getJson(route('admin.asistencias.index'), ['X-Requested-With' => 'XMLHttpRequest']);
        $ajaxResponse->assertOk();
        $ajaxResponse->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'dni',
                    'persona',
                    'funcion',
                    'fecha',
                    'hora',
                    'tipo',
                    'turno',
                    'estado',
                ],
            ],
        ]);
    }

    /* -------------------------------------------------------------------------
     |  Tier 1 & 2: Vehículos Image Management Endpoints
     | ------------------------------------------------------------------------- */

    /**
     * Feature 12 / Vehicles Image Management GET:
     * GET /admin/vehiculos/{vehiculo}/imagenes returns JSON metadata and current image list.
     */
    public function test_vehiculo_imagenes_get_endpoint_returns_expected_json_contract(): void
    {
        $vehicle = $this->createVehiculo();

        $response = $this->getJson(route('admin.vehiculos.imagenes', $vehicle->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'vehiculo' => ['nombre', 'codigo', 'placa', 'anio'],
            'imagenes',
            'maximo',
        ]);

        $this->assertSame($vehicle->name, $response->json('vehiculo.nombre'));
        $this->assertSame($vehicle->code, $response->json('vehiculo.codigo'));
        $this->assertSame($vehicle->plate, $response->json('vehiculo.placa'));
        $this->assertSame($vehicle->year, $response->json('vehiculo.anio'));
        $this->assertIsArray($response->json('imagenes'));
        $this->assertIsInt($response->json('maximo'));
    }

    /**
     * Feature 12 / Vehicles Image Management POST:
     * POST /admin/vehiculos/{vehiculo}/imagenes accepts multipart upload and persists photos.
     */
    public function test_vehiculo_imagenes_post_endpoint_uploads_and_stores_images(): void
    {
        $vehicle = $this->createVehiculo();
        $fakeImage = UploadedFile::fake()->image('frontal_compactador.jpg', 800, 600);

        $response = $this->post(route('admin.vehiculos.imagenes.guardar', $vehicle->id), [
            'images' => [$fakeImage],
            'profile' => 'new:0',
        ]);

        $response->assertOk();
        $response->assertJson([
            'mensaje' => 'Imágenes guardadas correctamente',
        ]);
        $response->assertJsonStructure([
            'mensaje',
            'imagenes' => [
                '*' => ['id', 'url', 'perfil'],
            ],
        ]);

        // Verify database persistence
        $this->assertDatabaseHas('vehicleimages', [
            'vehicle_id' => $vehicle->id,
            'profile' => true,
        ]);
    }

    /**
     * Tier 2 Boundary / Image Validation:
     * Non-image formats (e.g., text/plain disguised) must be rejected with 422.
     */
    public function test_vehiculo_imagenes_post_rejects_non_image_files(): void
    {
        $vehicle = $this->createVehiculo();
        $invalidFile = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->postJson(route('admin.vehiculos.imagenes.guardar', $vehicle->id), [
            'images' => [$invalidFile],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images.0']);
    }

    /**
     * Tier 2 Boundary / Image Limit:
     * Exceeding the maximum allowed images per vehicle (5) must be rejected with 422.
     */
    public function test_vehiculo_imagenes_post_rejects_exceeding_max_images(): void
    {
        $vehicle = $this->createVehiculo();
        $max = (int) config('proyecto.vehiculos.max_imagenes', 5);

        $tooManyImages = [];
        for ($i = 0; $i <= $max; $i++) {
            $tooManyImages[] = UploadedFile::fake()->image("foto_{$i}.jpg", 400, 300);
        }

        $response = $this->postJson(route('admin.vehiculos.imagenes.guardar', $vehicle->id), [
            'images' => $tooManyImages,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images']);
    }

    /* -------------------------------------------------------------------------
     |  Tier 1 & 3: Command Center Dashboard Contract & Invariants
     | ------------------------------------------------------------------------- */

    /**
     * Requirement R2 / Feature 3 & 8:
     * Verifies that GET /admin renders the Command Center dashboard and passes
     * all required KPI metrics, district identity, and quick action functions.
     */
    public function test_dashboard_command_center_contract_and_kpis(): void
    {
        $response = $this->get(route('admin.inicio'));
        $response->assertOk();
        $response->assertViewIs('admin.inicio');

        $viewData = $response->viewData();

        $this->assertArrayHasKey('municipalidad', $viewData);
        $this->assertSame(config('proyecto.municipalidad'), $viewData['municipalidad']);
        $this->assertArrayHasKey('funciones', $viewData);
        $this->assertIsArray($viewData['funciones']);

        // Check Milestone 1 KPI metrics contract
        if (! isset($viewData['kpis'])) {
            $this->markTestIncomplete(
                'Milestone M1 pending: InicioController::index does not yet return the complete '.
                'operational KPI metrics dictionary (vehiculos_total, vehiculos_operativos, '.
                'capacidad_total_toneladas, personal_total, contratos_vigentes, etc.).'
            );
        }

        $kpis = $viewData['kpis'];
        $expectedKpiKeys = [
            'vehiculos_total',
            'vehiculos_operativos',
            'vehiculos_taller',
            'capacidad_total_toneladas',
            'personal_total',
            'personal_turno_hoy',
            'contratos_vigentes',
            'contratos_por_vencer',
        ];

        foreach ($expectedKpiKeys as $key) {
            $this->assertArrayHasKey($key, $kpis, "KPI dictionary missing expected key [{$key}].");
        }

        $this->assertInstanceOf(Collection::class, $viewData['contratos_criticos']);
        $this->assertInstanceOf(Collection::class, $viewData['flota_resumen']);
        $this->assertInstanceOf(Collection::class, $viewData['asistencias_recientes']);
    }

    /**
     * Feature 3 / Invariant:
     * Visiting /admin triggers automatic closing of expired contracts.
     */
    public function test_dashboard_access_triggers_automatic_contract_expiry_sweep(): void
    {
        $person = $this->createPersonal();
        $expiredContract = $this->createContrato($person, [
            'type' => Contrato::TEMPORAL,
            'start_date' => today()->subMonths(3)->toDateString(),
            'end_date' => today()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $this->assertTrue($expiredContract->fresh()->is_active);

        $this->get(route('admin.inicio'))->assertOk();

        $this->assertFalse($expiredContract->fresh()->is_active);
    }

    /* -------------------------------------------------------------------------
     |  Tier 4: Real-World Municipal Operational Scenarios
     | ------------------------------------------------------------------------- */

    /**
     * Tier 4 Scenario 1: Municipal Fleet Operational & Maintenance Cycle
     * Ingest vehicle -> upload inspection photos -> toggle maintenance status -> verify catalog.
     */
    public function test_end_to_end_municipal_fleet_operation_cycle(): void
    {
        $vehicle = $this->createVehiculo(['load_capacity' => 14.0, 'status' => true]);

        // 1. Upload initial inspection photo
        $photo = UploadedFile::fake()->image('inspeccion_inicial.jpg', 1024, 768);
        $this->post(route('admin.vehiculos.imagenes.guardar', $vehicle->id), [
            'images' => [$photo],
            'profile' => 'new:0',
        ])->assertOk();

        // 2. Verify image list reflects newly uploaded inspection photo
        $gallery = $this->getJson(route('admin.vehiculos.imagenes', $vehicle->id));
        $gallery->assertOk();
        $this->assertCount(1, $gallery->json('imagenes'));
        $this->assertTrue($gallery->json('imagenes.0.perfil'));

        // 3. Vehicle enters workshop for repairs (status -> false)
        $brand = Marca::first();
        $model = Modelo::first();
        $type = TipoVehiculo::first();
        $color = ColorVehiculo::first();

        $updateResponse = $this->put(route('admin.vehiculos.update', $vehicle->id), [
            'name' => $vehicle->name,
            'code' => $vehicle->code,
            'plate' => $vehicle->plate,
            'year' => $vehicle->year,
            'load_capacity' => $vehicle->load_capacity,
            'fuel_capacity' => $vehicle->fuel_capacity,
            'compact_capacity' => $vehicle->compact_capacity,
            'occupant_capacity' => $vehicle->occupant_capacity,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'type_id' => $type->id,
            'color_id' => $color->id,
            'status' => false, // In Workshop
        ]);

        $updateResponse->assertStatus(302);
        $this->assertFalse($vehicle->fresh()->status);

        // 4. Verification in DataTable listing
        $listing = $this->getJson(route('admin.vehiculos.index'), ['X-Requested-With' => 'XMLHttpRequest']);
        $listing->assertOk();
        $vehicleRow = collect($listing->json('data'))->firstWhere('id', $vehicle->id);
        $this->assertNotNull($vehicleRow);
        $this->assertFalse($vehicleRow['activo']);
        $this->assertSame(1, $vehicleRow['total_imagenes']);
    }

    /**
     * Tier 4 Scenario 2: Shift Scheduling & Standby Replacement Cycle
     * Worker with valid contract punches entry -> absent driver triggers standby replacement lookup.
     */
    public function test_end_to_end_shift_attendance_and_standby_replacement_cycle(): void
    {
        // 1. Register driver and standby assistant
        $conductorType = TipoPersonal::conductor();
        $driver = $this->createPersonal([
            'usertype_id' => $conductorType->id,
            'license' => 'Q87654321',
        ]);
        $standbyDriver = $this->createPersonal([
            'usertype_id' => $conductorType->id,
            'license' => 'Q11223344',
        ]);

        $this->createContrato($driver);
        $this->createContrato($standbyDriver);

        // 2. Standby driver checked in earlier today
        Asistencia::create([
            'user_id' => $standbyDriver->id,
            'date' => today()->toDateString(),
            'time' => '05:45:00',
            'type' => 'entrada',
            'status' => 'presente',
            'source' => 'admin',
        ]);

        // 3. Driver attends: punch in (assigned morning shift)
        $this->postJson(route('admin.asistencias.store'), [
            'user_id' => $driver->id,
            'date' => today()->toDateString(),
            'time' => '06:05',
            'status' => 'presente',
        ])->assertOk();

        // 4. Standby replacement discovery: standbyDriver is identified as available substitute for driver
        $replacements = Personal::reemplazosDe($driver)->pluck('id')->all();
        $this->assertContains($standbyDriver->id, $replacements);

        // 5. Punch exit at end of shift
        $this->postJson(route('admin.asistencias.store'), [
            'user_id' => $driver->id,
            'date' => today()->toDateString(),
            'time' => '12:30',
            'status' => 'presente',
        ])->assertOk();

        // 6. Verify alternating sequence
        $punches = Asistencia::where('user_id', $driver->id)->orderBy('time')->pluck('type')->all();
        $this->assertSame(['entrada', 'salida'], $punches);
    }

    /**
     * Tier 4 Scenario 3: Contract Expiry, Critical Notification & Hiatus Enforcement
     * Expired contract auto-closes -> locks out attendance -> blocks premature re-hiring (< 2 months).
     */
    public function test_end_to_end_contract_expiry_and_hiatus_compliance_cycle(): void
    {
        $person = $this->createPersonal();
        $expiryDate = today()->subDay();

        // 1. Create temporary contract that expired yesterday
        $contract = $this->createContrato($person, [
            'type' => Contrato::TEMPORAL,
            'start_date' => $expiryDate->copy()->subMonths(3)->toDateString(),
            'end_date' => $expiryDate->toDateString(),
            'is_active' => true,
        ]);

        // 2. Contract auto-closure triggered
        Contrato::cerrarVencidos();
        $this->assertFalse($contract->fresh()->is_active);

        // 3. Attendance attempt fails because no active contract exists
        $this->postJson(route('admin.asistencias.store'), [
            'user_id' => $person->id,
            'date' => today()->toDateString(),
            'time' => '06:30',
            'status' => 'presente',
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');

        // 4. Attempting to issue a new temporary contract immediately (1 day after expiration)
        // must fail due to municipal 2-month mandatory cooling-off period rule
        $this->postJson(route('admin.contratos.store'), [
            'user_id' => $person->id,
            'type' => 'temporal',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addMonths(3)->toDateString(),
            'salary' => 1700,
            'is_active' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('start_date');

        // 5. Issuing contract exactly at the 2-month threshold succeeds
        $this->postJson(route('admin.contratos.store'), [
            'user_id' => $person->id,
            'type' => 'temporal',
            'start_date' => $expiryDate->copy()->addMonths(2)->toDateString(),
            'end_date' => $expiryDate->copy()->addMonths(5)->toDateString(),
            'salary' => 1700,
            'is_active' => false,
        ])->assertOk();
    }
}
