<?php

namespace Tests\Concurrencia;

use App\Models\ColorVehiculo;
use App\Models\Contrato;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\Vehiculo;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OperacionesSimultaneasTest extends TestCase
{
    public static function escenarios(): array
    {
        return array_map(fn ($nombre) => [$nombre], ['vacaciones', 'marcacion', 'contratos', 'personal', 'administradores', 'turnos', 'zonas', 'galeria']);
    }

    #[DataProvider('escenarios')]
    public function test_dos_procesos_respetan_las_invariantes(string $escenario): void
    {
        if (getenv('RSU_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Requiere MySQL descartable y phpunit.mysql.xml.');
        }
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringEndsWith('_pruebas', DB::connection()->getDatabaseName());
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);
        $admin = User::factory()->create();
        $otro = User::factory()->create();
        $persona = Personal::create(['name' => 'Ana', 'last_name' => 'Prueba', 'dni' => '71234567', 'email' => 'ana@test.local', 'birthdate' => '1990-01-01',
            'address' => 'Av Principal 123', 'password' => 'secret123', 'status' => true, 'usertype_id' => TipoPersonal::ayudante()->id]);
        $contrato = Contrato::create(['user_id' => $persona->id, 'type' => 'permanente', 'start_date' => today()->subYear(), 'salary' => 1500, 'is_active' => true]);
        $operaciones = [];
        if ($escenario === 'vacaciones') {
            $vacacion = Vacacion::create(['user_id' => $persona->id, 'start_date' => today()->addDay(), 'end_date' => today()->addDays(2), 'days' => 2, 'status' => 'pendiente']);
            $operaciones = [['PATCH', "/admin/vacaciones/{$vacacion->id}/aprobar", ['version' => 1]], ['PATCH', "/admin/vacaciones/{$vacacion->id}/rechazar", ['version' => 1]]];
        } elseif ($escenario === 'marcacion') {
            $operaciones = array_fill(0, 2, ['POST', '/admin/asistencias/marcacion', ['dni' => $persona->dni, 'password' => 'secret123']]);
        } elseif ($escenario === 'contratos') {
            $contrato->update(['is_active' => false]);
            $datos = ['user_id' => $persona->id, 'type' => 'permanente', 'start_date' => today()->toDateString(), 'salary' => 1500, 'is_active' => true];
            $operaciones = array_fill(0, 2, ['POST', '/admin/contratos', $datos]);
        } elseif ($escenario === 'personal') {
            $contrato->update(['is_active' => false]);
            $datos = $persona->only(['name', 'last_name', 'dni', 'email', 'birthdate', 'address', 'usertype_id']);
            $datos['birthdate'] = '1990-01-01';
            $operaciones = [['POST', '/admin/contratos', ['user_id' => $persona->id, 'type' => 'permanente', 'start_date' => today()->toDateString(), 'salary' => 1500, 'is_active' => true]],
                ['PUT', "/admin/personal/{$persona->id}", $datos + ['status' => false, 'version' => 1]]];
        } elseif ($escenario === 'administradores') {
            $operaciones = [['PUT', "/admin/usuarios/{$otro->id}", ['name' => $otro->name, 'email' => $otro->email, 'status' => false, 'version' => 1]],
                ['PUT', "/admin/usuarios/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'status' => false, 'version' => 1]]];
        } elseif ($escenario === 'turnos') {
            DB::table('schedules')->delete();
            $operaciones = [['POST', '/admin/turnos', ['name' => 'Uno', 'time_start' => '08:00', 'time_end' => '12:00']], ['POST', '/admin/turnos', ['name' => 'Dos', 'time_start' => '09:00', 'time_end' => '13:00']]];
        } elseif ($escenario === 'zonas') {
            $datos = ['department' => 'Lambayeque', 'province' => 'Chiclayo', 'district' => 'José Leonardo Ortiz', 'status' => true,
                'coordinates' => [['latitude' => -6.748, 'longitude' => -79.845], ['latitude' => -6.748, 'longitude' => -79.830], ['latitude' => -6.760, 'longitude' => -79.830], ['latitude' => -6.760, 'longitude' => -79.845]]];
            $operaciones = [['POST', '/admin/zonas', ['name' => 'Uno'] + $datos], ['POST', '/admin/zonas', ['name' => 'Dos'] + $datos]];
        } else {
            $marca = Marca::create(['name' => 'Marca']);
            $modelo = Modelo::create(['name' => 'Modelo', 'code' => 'M', 'brand_id' => $marca->id]);
            $tipo = TipoVehiculo::create(['name' => 'Tipo']);
            $color = ColorVehiculo::create(['name' => 'Color', 'code' => '#123456']);
            $vehiculo = Vehiculo::create(['name' => 'Vehículo', 'code' => 'V1', 'plate' => 'AAA123', 'year' => 2024, 'brand_id' => $marca->id, 'model_id' => $modelo->id, 'type_id' => $tipo->id, 'color_id' => $color->id]);
            foreach (range(1, 11) as $numero) {
                $vehiculo->imagenes()->create(['image' => '/images/sin-vehiculo.svg', 'profile' => $numero === 1]);
            }
            $operaciones = array_fill(0, 2, ['IMAGEN', "/admin/vehiculos/{$vehiculo->id}/imagenes", ['version' => 1]]);
        }
        $barrera = storage_path('framework/testing/barrera-'.bin2hex(random_bytes(8)));
        if (! is_dir(dirname($barrera))) {
            mkdir(dirname($barrera), 0777, true);
        }
        $procesos = [];
        try {
            foreach ($operaciones as $indice => [$metodo,$url,$datos]) {
                $actor = $indice === 1 ? $otro : $admin;
                $proceso = new Process([PHP_BINARY, __DIR__.'/operacion.php', $barrera, (string) $indice, (string) $actor->id, $metodo, $url, json_encode($datos)], base_path(), timeout: 40);
                $proceso->start();
                $procesos[] = $proceso;
            }
            $limite = microtime(true) + 20;
            while (! is_file($barrera.'.0') || ! is_file($barrera.'.1')) {
                if (microtime(true) > $limite) {
                    $this->fail('Los procesos no alcanzaron la barrera: '.implode(' ', array_map(fn ($p) => $p->getErrorOutput(), $procesos)));
                }
                usleep(10000);
            }
            file_put_contents($barrera, 'continuar');
            $estados = [];
            foreach ($procesos as $proceso) {
                $proceso->wait();
                $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
                $estado = json_decode($proceso->getOutput(), true)['estado'] ?? 0;
                $this->assertContains($estado, [200, 403, 409, 422], $proceso->getOutput());
                $estados[] = $estado;
            }
            $this->assertSame(1, count(array_filter($estados, fn ($e) => $e === 200)), json_encode($estados));
            if ($escenario === 'marcacion') {
                $this->assertSame(1, $persona->asistencias()->count());
            }
            if ($escenario === 'contratos') {
                $this->assertSame(1, $persona->contratos()->where('is_active', true)->count());
            }
            if ($escenario === 'personal') {
                $this->assertFalse(! $persona->fresh()->status && $persona->contratos()->where('is_active', true)->exists());
            }
            if ($escenario === 'administradores') {
                $this->assertSame(1, User::whereNull('usertype_id')->where('status', true)->count());
            }
            if ($escenario === 'turnos') {
                $this->assertDatabaseCount('schedules', 1);
            }
            if ($escenario === 'zonas') {
                $this->assertDatabaseCount('zones', 1);
            }
            if ($escenario === 'galeria') {
                $this->assertSame(12, $vehiculo->imagenes()->count());
            }
        } finally {
            foreach ($procesos as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop();
                }
            }
            foreach ([$barrera, $barrera.'.0', $barrera.'.1'] as $archivo) {
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }
        }
    }
}
