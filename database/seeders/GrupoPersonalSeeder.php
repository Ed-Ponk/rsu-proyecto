<?php

namespace Database\Seeders;

use App\Models\GrupoPersonal;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\Vehiculo;
use App\Models\Zona;
use Illuminate\Database\Seeder;

/** Grupos de ejemplo (solo desarrollo): cada uno con su turno, zona, vehículo, días, conductor y ayudantes. */
class GrupoPersonalSeeder extends Seeder
{
    public function run(): void
    {
        $conContrato = fn ($c) => $c->vigente();
        $conductores = Personal::where('usertype_id', TipoPersonal::conductor()?->id)->activos()->whereHas('contratos', $conContrato)->orderBy('users.id')->get();
        $ayudantes = Personal::where('usertype_id', TipoPersonal::ayudante()?->id)->activos()->whereHas('contratos', $conContrato)->orderBy('users.id')->get();
        $zonas = Zona::where('status', true)->orderBy('id')->get();
        $vehiculos = Vehiculo::where('status', true)->orderBy('id')->get();
        $turnos = Turno::orderBy('time_start')->get();

        $definiciones = [
            ['Grupo A', [1, 3, 5]],
            ['Grupo B', [2, 4, 6]],
        ];

        foreach ($definiciones as $i => [$nombre, $dias]) {
            $conductor = $conductores->get($i);
            $vehiculo = $vehiculos->get($i);
            $zona = $zonas->get($i);
            $turno = $turnos->get($i % max(1, $turnos->count()));

            if (! $conductor || ! $vehiculo || ! $zona || ! $turno) {
                continue;
            }

            $cuantos = max(1, $vehiculo->occupant_capacity - 1);
            $elegidos = $ayudantes->slice($i * 2, min($cuantos, 2));

            if ($elegidos->isEmpty() || GrupoPersonal::where('name', $nombre)->exists()) {
                continue;
            }

            $grupo = GrupoPersonal::create([
                'name' => $nombre, 'shift_id' => $turno->id, 'zone_id' => $zona->id, 'vehicle_id' => $vehiculo->id,
                'weekdays' => $dias, 'driver_id' => $conductor->id, 'status' => true,
            ]);

            foreach ($elegidos->values() as $posicion => $ayudante) {
                $grupo->ayudantes()->attach($ayudante->id, ['position' => $posicion + 1]);
            }
        }
    }
}
