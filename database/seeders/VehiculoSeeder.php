<?php

namespace Database\Seeders;

use App\Models\ColorVehiculo;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class VehiculoSeeder extends Seeder
{
    /**
     * Cada vehículo coincide con sus fotos de public/images/vehicles/<codigo>-<n>.jpg
     * (marca, modelo, tipo y color son los que se ven en las imágenes).
     */
    public function run(): void
    {
        $flota = [
            [
                'code' => 'VEH-001', 'name' => 'Vehículo 01', 'plate' => 'CLO-008', 'year' => 2010,
                'load_capacity' => 9.50, 'fuel_capacity' => 180, 'compact_capacity' => 8.5, 'occupant_capacity' => 3,
                'description' => 'Camión recolector con compactador para la zona centro.',
                'tipo' => 'Camión Recolector', 'marca' => 'Mercedes Benz', 'modelo' => 'Atego', 'color' => 'Blanco',
            ],
            [
                'code' => 'VEH-002', 'name' => 'Vehículo 02', 'plate' => 'CLO-002', 'year' => 2008,
                'load_capacity' => 4.50, 'fuel_capacity' => 100, 'compact_capacity' => 0, 'occupant_capacity' => 2,
                'description' => 'Volquete para residuos de construcción y desmonte.',
                'tipo' => 'Volquete', 'marca' => 'Toyota', 'modelo' => 'Dyna', 'color' => 'Rojo',
            ],
            [
                'code' => 'VEH-003', 'name' => 'Vehículo 03', 'plate' => 'CLO-004', 'year' => 2001,
                'load_capacity' => 12.00, 'fuel_capacity' => 220, 'compact_capacity' => 11.0, 'occupant_capacity' => 3,
                'description' => 'Compactador de alto volumen para las rutas principales.',
                'tipo' => 'Compactador', 'marca' => 'Volvo', 'modelo' => 'FM', 'color' => 'Azul',
            ],
            [
                'code' => 'VEH-004', 'name' => 'Vehículo 04', 'plate' => 'CLO-007', 'year' => 2020,
                'load_capacity' => 5.20, 'fuel_capacity' => 120, 'compact_capacity' => 4.5, 'occupant_capacity' => 3,
                'description' => 'Camión recolector compacto para calles angostas.',
                'tipo' => 'Camión Recolector', 'marca' => 'Hyundai', 'modelo' => 'Mighty', 'color' => 'Blanco',
            ],
        ];

        foreach ($flota as $fila) {
            $marca = Marca::firstWhere('name', $fila['marca']);
            $modelo = Modelo::where('brand_id', $marca?->id)->firstWhere('name', $fila['modelo']);
            $tipo = TipoVehiculo::firstWhere('name', $fila['tipo']);
            $color = ColorVehiculo::firstWhere('name', $fila['color']);

            if (! $marca || ! $modelo || ! $tipo || ! $color) {
                continue;
            }

            Vehiculo::updateOrCreate(
                ['code' => $fila['code']],
                [
                    'name' => $fila['name'],
                    'plate' => $fila['plate'],
                    'year' => $fila['year'],
                    'load_capacity' => $fila['load_capacity'],
                    'fuel_capacity' => $fila['fuel_capacity'],
                    'compact_capacity' => $fila['compact_capacity'],
                    'occupant_capacity' => $fila['occupant_capacity'],
                    'description' => $fila['description'],
                    'status' => true,
                    'brand_id' => $marca->id,
                    'model_id' => $modelo->id,
                    'type_id' => $tipo->id,
                    'color_id' => $color->id,
                ],
            );
        }
    }
}
