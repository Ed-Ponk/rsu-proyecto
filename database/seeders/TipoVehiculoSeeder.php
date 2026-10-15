<?php

namespace Database\Seeders;

use App\Models\TipoVehiculo;
use Illuminate\Database\Seeder;

class TipoVehiculoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Camión Recolector' => 'Vehículo que recoge los residuos de las calles',
            'Compactador' => 'Vehículo que compacta los residuos para transportar más carga',
            'Volquete' => 'Vehículo de carga con tolva volcable',
        ];

        foreach ($tipos as $nombre => $descripcion) {
            TipoVehiculo::updateOrCreate(['name' => $nombre], ['description' => $descripcion]);
        }
    }
}
