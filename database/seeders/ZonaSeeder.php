<?php

namespace Database\Seeders;

use App\Models\Zona;
use Illuminate\Database\Seeder;

class ZonaSeeder extends Seeder
{
    /**
     * Cinco zonas de ejemplo en José Leonardo Ortiz, dispuestas en cuadrícula (oeste, centro-norte/centro/centro-sur y este).
     * Cada polígono se define por sus esquinas [latitud, longitud].
     */
    public function run(): void
    {
        $zonas = [
            'Zona Norte' => ['Sector norte de José Leonardo Ortiz', 180.50, [[-6.7480, -79.8450], [-6.7480, -79.8300], [-6.7600, -79.8300], [-6.7600, -79.8450]]],
            'Zona Centro' => ['Sector centro de José Leonardo Ortiz', 320.00, [[-6.7600, -79.8450], [-6.7600, -79.8300], [-6.7700, -79.8300], [-6.7700, -79.8450]]],
            'Zona Sur' => ['Sector sur de José Leonardo Ortiz', 210.75, [[-6.7700, -79.8450], [-6.7700, -79.8300], [-6.7800, -79.8300], [-6.7800, -79.8450]]],
            'Zona Oeste' => ['Sector oeste de José Leonardo Ortiz', 150.25, [[-6.7480, -79.8600], [-6.7480, -79.8450], [-6.7800, -79.8450], [-6.7800, -79.8600]]],
            'Zona Este' => ['Sector este de José Leonardo Ortiz', 275.00, [[-6.7480, -79.8300], [-6.7480, -79.8150], [-6.7800, -79.8150], [-6.7800, -79.8300]]],
        ];

        foreach ($zonas as $nombre => [$descripcion, $residuos, $vertices]) {
            $zona = Zona::firstOrCreate(['name' => $nombre], [
                'department' => 'Lambayeque', 'province' => 'Chiclayo', 'district' => 'José Leonardo Ortiz',
                'description' => $descripcion, 'average_waste' => $residuos, 'status' => true,
            ]);

            if ($zona->wasRecentlyCreated) {
                foreach ($vertices as $posicion => [$lat, $lng]) {
                    $zona->coordenadas()->create(['latitude' => $lat, 'longitude' => $lng, 'position' => $posicion + 1]);
                }
            }
        }
    }
}
