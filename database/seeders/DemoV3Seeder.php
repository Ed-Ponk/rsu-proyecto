<?php

namespace Database\Seeders;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\Vehiculo;
use App\Models\Zona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoV3Seeder extends Seeder
{
    public function run(): void
    {
        $base = basename((string) DB::connection()->getDatabaseName());
        $clave = getenv('RSU_DEMO_PASSWORD');
        if (! app()->environment('local') || ! config('rsu.demostracion')
            || ! str_starts_with($base, 'rsu_v3_demo') || strlen((string) $clave) < 12) {
            throw new RuntimeException('La demostración requiere modo local, RSU_DEMO_DATA y una base aislada rsu_v3_demo con clave ficticia propia.');
        }

        DB::transaction(function () use ($clave) {
            $ahora = now();
            $turno = Turno::all()->first(function (Turno $turno) use ($ahora) {
                $dia = $turno->jornadaPara($ahora);

                return $ahora->gte($turno->inicioDe($dia)) && $ahora->lt($turno->finDe($dia));
            });
            if (! $turno) {
                throw new RuntimeException('No existe un turno vigente en este momento.');
            }
            $dia = $turno->jornadaPara($ahora);
            $zona = Zona::where('status', true)->firstOrFail();
            $personas = collect([
                ['99997011', 'Mateo', 'conductor', 'Q99997011', '999970111'],
                ['99997012', 'Sofía', 'ayudante', null, '999970112'],
                ['99997013', 'Valeria', 'ayudante', null, '999970113'],
                ['99997014', 'Diego', 'conductor', 'Q99997014', '999970114'],
            ])->map(function ($datos, $i) use ($clave) {
                [$dni, $nombre, $rol, $licencia, $telefono] = $datos;
                $p = Personal::updateOrCreate(['dni' => $dni], [
                    'usertype_id' => ($rol === 'conductor' ? TipoPersonal::conductor() : TipoPersonal::ayudante())->id,
                    'name' => $nombre, 'last_name' => 'Demostración', 'birthdate' => '1991-01-01',
                    'email' => 'demo'.($i + 1).'@rsu.test', 'address' => 'Dirección ficticia RSU',
                    'password' => $clave, 'phone' => $telefono, 'status' => true, 'license' => $licencia,
                ]);
                Contrato::firstOrCreate(['user_id' => $p->id, 'is_active' => true], [
                    'type' => Contrato::PERMANENTE, 'start_date' => today()->subYear()->toDateString(), 'salary' => 1500,
                ]);

                return $p;
            });
            $vehiculo = Vehiculo::where('code', 'V3-DEMO')->first();
            if (! $vehiculo) {
                $vehiculo = Vehiculo::firstOrFail()->replicate()->fill(['name' => 'Camión de demostración V3', 'plate' => 'ZZZ-997', 'code' => 'V3-DEMO', 'status' => true]);
                $vehiculo->save();
            }
            foreach ([$dia, $ahora->copy()->addWeek()->toDateString()] as $fecha) {
                $p = Programacion::firstOrCreate([
                    'date' => $fecha, 'shift_id' => $turno->id, 'driver_id' => $personas[0]->id,
                ], ['zone_id' => $zona->id, 'vehicle_id' => $vehiculo->id, 'status' => Programacion::PROGRAMADO]);
                $p->asignarAyudantes([$personas[1]->id, $personas[2]->id]);
            }
            foreach ($personas->take(getenv('RSU_DEMO_ALL_PRESENT') === '1' ? 3 : 2) as $p) {
                Asistencia::firstOrCreate([
                    'user_id' => $p->id, 'shift_date' => $dia, 'schedule_id' => $turno->id, 'type' => Asistencia::ENTRADA,
                ], ['date' => $dia, 'time' => $turno->time_start, 'status' => Asistencia::PRESENTE, 'source' => 'admin']);
            }
        });
    }
}
