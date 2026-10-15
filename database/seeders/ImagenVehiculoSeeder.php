<?php

namespace Database\Seeders;

use App\Models\ImagenVehiculo;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class ImagenVehiculoSeeder extends Seeder
{
    /**
     * Fotos reales guardadas dentro del proyecto (public/images/vehicles) para que viajen con la carpeta.
     * Se nombran <codigo>-<n>.jpg (VEH-001 → veh-001-1.jpg, veh-001-2.jpg...); la primera es la imagen de perfil.
     */
    public function run(): void
    {
        foreach (Vehiculo::orderBy('code')->get() as $vehiculo) {
            $archivos = glob(public_path('images/vehicles/'.strtolower($vehiculo->code).'-*.jpg')) ?: [];

            if (! $archivos) {
                continue;
            }

            natsort($archivos);

            // Solo se renuevan las imágenes de ejemplo; las que se hayan subido desde el sistema se respetan.
            ImagenVehiculo::where('vehicle_id', $vehiculo->id)
                ->where(fn ($q) => $q->where('image', 'like', '/images/vehicles/%')->orWhere('image', 'like', '/uploads/vehicle_images/seed/%'))
                ->delete();

            $yaTienePerfil = ImagenVehiculo::where('vehicle_id', $vehiculo->id)->where('profile', true)->exists();

            foreach (array_values($archivos) as $posicion => $ruta) {
                ImagenVehiculo::create([
                    'vehicle_id' => $vehiculo->id,
                    'image' => '/images/vehicles/'.basename($ruta),
                    'profile' => ! $yaTienePerfil && $posicion === 0,
                ]);
            }
        }
    }
}
