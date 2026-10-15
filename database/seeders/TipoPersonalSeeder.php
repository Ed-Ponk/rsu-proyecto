<?php

namespace Database\Seeders;

use App\Models\TipoPersonal;
use Illuminate\Database\Seeder;

class TipoPersonalSeeder extends Seeder
{
    /** Las dos funciones obligatorias del sistema (no se pueden borrar ni renombrar). */
    public function run(): void
    {
        $funciones = [
            TipoPersonal::CONDUCTOR => 'Personal autorizado para conducir los vehículos',
            TipoPersonal::AYUDANTE => 'Personal de apoyo en la recolección',
        ];

        foreach ($funciones as $nombre => $descripcion) {
            TipoPersonal::updateOrCreate(
                ['name' => $nombre],
                ['description' => $descripcion, 'is_system' => true],
            );
        }
    }
}
