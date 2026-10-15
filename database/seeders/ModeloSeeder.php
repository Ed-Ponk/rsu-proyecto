<?php

namespace Database\Seeders;

use App\Models\Marca;
use App\Models\Modelo;
use Illuminate\Database\Seeder;

class ModeloSeeder extends Seeder
{
    public function run(): void
    {
        // marca => [nombre del modelo => [código, descripción]]
        $catalogo = [
            'Mercedes Benz' => [
                'Atego' => ['ATEGO-MB', 'Camión resistente para trabajo pesado en ciudad.'],
                'Axor' => ['AXOR-MB', 'Camión de gran capacidad para rutas largas.'],
            ],
            'Volvo' => [
                'FM' => ['FM-VOL', 'Camión pesado para recolección y compactación.'],
                'FMX' => ['FMX-VOL', 'Camión de obra y volquete.'],
            ],
            'Hyundai' => [
                'HD78' => ['HD78-HYU', 'Camión liviano para zonas urbanas.'],
                'HD120' => ['HD120-HYU', 'Camión mediano de uso comercial.'],
                'Mighty' => ['MIGHTY-HYU', 'Camión mediano con compactador para recolección urbana.'],
            ],
            'Toyota' => [
                'Dyna' => ['DYNA-TOY', 'Camión liviano de carga.'],
            ],
            'Hino' => [
                '300' => ['300-HINO', 'Camión liviano de bajo consumo.'],
                '500' => ['500-HINO', 'Camión pesado de alto rendimiento.'],
            ],
            'Isuzu' => [
                'NPR' => ['NPR-ISU', 'Camión liviano para recolección urbana.'],
                'FVR' => ['FVR-ISU', 'Camión mediano para compactadoras.'],
            ],
        ];

        foreach ($catalogo as $nombreMarca => $modelos) {
            $marca = Marca::firstWhere('name', $nombreMarca);

            if (! $marca) {
                continue;
            }

            foreach ($modelos as $nombre => [$codigo, $descripcion]) {
                Modelo::updateOrCreate(
                    ['brand_id' => $marca->id, 'name' => $nombre],
                    ['code' => $codigo, 'description' => $descripcion],
                );
            }
        }
    }
}
