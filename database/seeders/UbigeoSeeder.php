<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Carga departamentos, provincias y distritos desde config/ubigeo.php. Se puede repetir sin duplicar. */
class UbigeoSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = now();

        foreach (config('ubigeo.departamentos') as $departamento => $provincias) {
            DB::table('departments')->insertOrIgnore(['name' => $departamento, 'created_at' => $ahora, 'updated_at' => $ahora]);
            $idDepartamento = DB::table('departments')->where('name', $departamento)->value('id');

            foreach ($provincias as $provincia => $distritos) {
                DB::table('provinces')->insertOrIgnore(['department_id' => $idDepartamento, 'name' => $provincia, 'created_at' => $ahora, 'updated_at' => $ahora]);
                $idProvincia = DB::table('provinces')->where('department_id', $idDepartamento)->where('name', $provincia)->value('id');

                foreach ($distritos as $distrito => [$latitud, $longitud]) {
                    DB::table('districts')->updateOrInsert(
                        ['province_id' => $idProvincia, 'name' => $distrito],
                        ['latitude' => $latitud, 'longitude' => $longitud, 'created_at' => $ahora, 'updated_at' => $ahora],
                    );
                }
            }
        }
    }
}
