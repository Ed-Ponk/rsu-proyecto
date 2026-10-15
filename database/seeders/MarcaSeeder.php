<?php

namespace Database\Seeders;

use App\Models\Marca;
use Illuminate\Database\Seeder;

class MarcaSeeder extends Seeder
{
    /**
     * Los logos están dentro del proyecto (public/images/brands), así que al comprimir la carpeta y llevarla
     * a otra computadora las imágenes viajan con ella. En la BD se guarda la ruta relativa a /public.
     */
    public function run(): void
    {
        $marcas = [
            'Mercedes Benz' => ['mercedes-benz.jpg', 'Marca alemana de vehículos de alta calidad.'],
            'Volvo' => ['volvo.jpg', 'Marca sueca especializada en vehículos pesados.'],
            'Hyundai' => ['hyundai.jpg', 'Marca coreana de vehículos comerciales.'],
            'Toyota' => ['toyota.jpg', 'Marca japonesa reconocida mundialmente.'],
            'Hino' => ['hino.jpg', 'Fabricante japonés de camiones medianos y pesados.'],
            'Isuzu' => ['isuzu.jpg', 'Fabricante japonés de camiones y motores diésel.'],
        ];

        foreach ($marcas as $nombre => [$archivo, $descripcion]) {
            Marca::updateOrCreate(
                ['name' => $nombre],
                ['description' => $descripcion, 'logo' => "/images/brands/{$archivo}"],
            );
        }
    }
}
