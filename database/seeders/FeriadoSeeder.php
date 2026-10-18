<?php

namespace Database\Seeders;

use App\Models\Feriado;
use App\Services\CargadorFeriados;
use Illuminate\Database\Seeder;

class FeriadoSeeder extends Seeder
{
    /** Feriados oficiales del año actual (calendario propio: el seeder no necesita internet). */
    public function run(): void
    {
        foreach ((new CargadorFeriados)->calendarioPropio(today()->year) as $fecha => $descripcion) {
            Feriado::firstOrCreate(['date' => $fecha], ['description' => $descripcion, 'status' => true]);
        }
    }
}
