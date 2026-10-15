<?php

namespace Database\Seeders;

use App\Models\ColorVehiculo;
use Illuminate\Database\Seeder;

class ColorVehiculoSeeder extends Seeder
{
    public function run(): void
    {
        $colores = [
            'Gris' => ['#808080', 'Gris metálico'],
            'Blanco' => ['#FFFFFF', 'Blanco perlado'],
            'Azul' => ['#104CAD', 'Azul institucional'],
            'Verde' => ['#16A34A', 'Verde ecológico'],
            'Amarillo' => ['#FACC15', 'Amarillo de seguridad'],
            'Naranja' => ['#F97316', 'Naranja de señalización'],
            'Rojo' => ['#DC2626', 'Rojo de seguridad'],
        ];

        foreach ($colores as $nombre => [$codigo, $descripcion]) {
            ColorVehiculo::updateOrCreate(['name' => $nombre], ['code' => $codigo, 'description' => $descripcion]);
        }
    }
}
