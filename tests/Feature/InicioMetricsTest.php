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
use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InicioMetricsTest extends TestCase
{
    use RefreshDatabase;

    private int $dniCounter = 30000000;

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    private function createPersonal(array $overrides = []): Personal
    {
        $dni = (string) ++$this->dniCounter;

        return Personal::create($overrides + [
            'dni' => $dni,
            'usertype_id' => TipoPersonal::ayudante()->id,
            'name' => 'Empleado',
            'last_name' => "Prueba {$dni}",
            'birthdate' => '1992-03-15',
            'email' => "emp{$dni}@muni-jlo.gob.pe",
            'address' => 'Av. Balta 100, JLO',
            'password' => 'secret123',
            'status' => true,
        ]);
    }

    private function createVehicle(array $overrides = []): Vehiculo
    {
        static $vCounter = 1;
        $idx = $vCounter++;

        $marca = Marca::firstOrCreate(['name' => 'Mercedes-Benz']);
        $modelo = Modelo::firstOrCreate(['brand_id' => $marca->id, 'name' => 'Atego'], ['code' => 'MB-ATEGO']);
        $tipo = TipoVehiculo::firstOrCreate(['name' => 'Compactador']);
        $color = ColorVehiculo::firstOrCreate(['name' => 'Verde'], ['code' => '#166534']);

        return Vehiculo::create($overrides + [
            'name' => "Compactador {$idx}",
            'code' => "V-M{$idx}",
            'plate' => "EAA-{$idx}00",
            'year' => 2023,
            'load_capacity' => 10.0,
            'fuel_capacity' => 150.0,
            'compact_capacity' => 8.0,
            'occupant_capacity' => 3,
            'status' => true,
            'brand_id' => $marca->id,
            'model_id' => $modelo->id,
            'type_id' => $tipo->id,
            'color_id' => $color->id,
        ]);
    }

    public function test_inicio_returns_graceful_defaults_on_empty_tables(): void
    {
        $response = $this->get(route('admin.inicio'));
        $response->assertOk();

        $data = $response->viewData();
        $this->assertArrayHasKey('kpis', $data);
        $this->assertSame(0, $data['kpis']['vehiculos_total']);
        $this->assertSame(0, $data['kpis']['vehiculos_operativos']);
        $this->assertSame(0, $data['kpis']['vehiculos_taller']);
        $this->assertEquals(0.0, $data['kpis']['capacidad_total_toneladas']);
        $this->assertSame(0, $data['kpis']['personal_total']);
        $this->assertSame(0, $data['kpis']['personal_turno_hoy']);
        $this->assertSame(0, $data['kpis']['contratos_vigentes']);
        $this->assertSame(0, $data['kpis']['contratos_por_vencer']);

        $this->assertTrue($data['contratos_criticos']->isEmpty());
        $this->assertTrue($data['flota_resumen']->isEmpty());
        $this->assertTrue($data['asistencias_recientes']->isEmpty());
    }

    public function test_inicio_computes_exact_fleet_and_tonnage_kpis(): void
    {
        // 2 operativos (10 + 5.5 = 15.5 ton), 1 en taller (8 ton)
        $this->createVehicle(['load_capacity' => 10.0, 'status' => true]);
        $this->createVehicle(['load_capacity' => 5.5, 'status' => true]);
        $this->createVehicle(['load_capacity' => 8.0, 'status' => false]);

        $response = $this->get(route('admin.inicio'));
        $response->assertOk();

        $kpis = $response->viewData('kpis');
        $this->assertSame(3, $kpis['vehiculos_total']);
        $this->assertSame(2, $kpis['vehiculos_operativos']);
        $this->assertSame(1, $kpis['vehiculos_taller']);
        $this->assertEquals(23.5, $kpis['capacidad_total_toneladas']);
    }

    public function test_inicio_computes_personnel_and_shift_attendance_today(): void
    {
        $p1 = $this->createPersonal();
        $p2 = $this->createPersonal();
        $p3 = $this->createPersonal();

        // P1 y P2 tienen contrato vigente
        Contrato::create([
            'user_id' => $p1->id, 'type' => Contrato::PERMANENTE,
            'start_date' => today()->subMonth()->toDateString(), 'salary' => 1500, 'is_active' => true,
        ]);
        Contrato::create([
            'user_id' => $p2->id, 'type' => Contrato::PERMANENTE,
            'start_date' => today()->subMonth()->toDateString(), 'salary' => 1500, 'is_active' => true,
        ]);

        // P1 marcó hoy como presente
        $turno = Turno::first();
        Asistencia::create([
            'user_id' => $p1->id, 'date' => today()->toDateString(), 'time' => '06:15:00',
            'schedule_id' => $turno?->id, 'type' => Asistencia::ENTRADA, 'status' => Asistencia::PRESENTE,
            'source' => 'admin', 'registered_by' => $this->admin->id,
        ]);

        $response = $this->get(route('admin.inicio'));
        $response->assertOk();

        $kpis = $response->viewData('kpis');
        $this->assertSame(3, $kpis['personal_total']);
        $this->assertSame(2, $kpis['contratos_vigentes']);
        $this->assertSame(1, $kpis['personal_turno_hoy']);

        $asistenciasRecientes = $response->viewData('asistencias_recientes');
        $this->assertCount(1, $asistenciasRecientes);
        $this->assertSame($p1->id, $asistenciasRecientes->first()->user_id);
    }

    public function test_inicio_detects_critical_contracts_expiring_within_30_days(): void
    {
        $p1 = $this->createPersonal();
        $p2 = $this->createPersonal();
        $p3 = $this->createPersonal();

        // Contrato crítico: vence en 10 días
        $critico = Contrato::create([
            'user_id' => $p1->id, 'type' => Contrato::TEMPORAL,
            'start_date' => today()->subMonths(2)->toDateString(),
            'end_date' => today()->addDays(10)->toDateString(),
            'salary' => 1600, 'is_active' => true,
        ]);

        // Contrato no crítico: vence en 60 días
        Contrato::create([
            'user_id' => $p2->id, 'type' => Contrato::TEMPORAL,
            'start_date' => today()->subMonth()->toDateString(),
            'end_date' => today()->addDays(60)->toDateString(),
            'salary' => 1600, 'is_active' => true,
        ]);

        // Contrato permanente: no vence
        Contrato::create([
            'user_id' => $p3->id, 'type' => Contrato::PERMANENTE,
            'start_date' => today()->subYear()->toDateString(),
            'salary' => 2000, 'is_active' => true,
        ]);

        $response = $this->get(route('admin.inicio'));
        $response->assertOk();

        $kpis = $response->viewData('kpis');
        $this->assertSame(3, $kpis['contratos_vigentes']);
        $this->assertSame(1, $kpis['contratos_por_vencer']);

        $contratosCriticos = $response->viewData('contratos_criticos');
        $this->assertCount(1, $contratosCriticos);
        $this->assertSame($critico->id, $contratosCriticos->first()->id);
        $this->assertSame(10, $contratosCriticos->first()->dias_restantes);
    }

    public function test_inicio_provides_fleet_summary_breakdown(): void
    {
        $compactador = TipoVehiculo::firstOrCreate(['name' => 'Compactador']);
        $volquete = TipoVehiculo::firstOrCreate(['name' => 'Volquete']);

        $this->createVehicle(['type_id' => $compactador->id, 'status' => true]);
        $this->createVehicle(['type_id' => $compactador->id, 'status' => false]);
        $this->createVehicle(['type_id' => $volquete->id, 'status' => true]);

        $response = $this->get(route('admin.inicio'));
        $response->assertOk();

        $flotaResumen = $response->viewData('flota_resumen');
        $this->assertNotEmpty($flotaResumen);

        $tipoCompactador = $flotaResumen->firstWhere('id', $compactador->id);
        $this->assertNotNull($tipoCompactador);
        $this->assertEquals(2, $tipoCompactador->total);
        $this->assertEquals(1, $tipoCompactador->operativos);
        $this->assertEquals(1, $tipoCompactador->taller);
    }
}
