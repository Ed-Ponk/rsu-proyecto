<?php

namespace Database\Seeders;

use App\Models\TipoPersonal;
use Illuminate\Database\Seeder;

class TipoPersonalSeeder extends Seeder
{
    /** Las dos funciones del sistema. Se buscan por su clave interna para respetar un nombre ya editado. */
    public function run(): void
    {
        $funciones = [
            TipoPersonal::CODIGO_CONDUCTOR => [TipoPersonal::CONDUCTOR, 'Personal autorizado para conducir los vehículos'],
            TipoPersonal::CODIGO_AYUDANTE => [TipoPersonal::AYUDANTE, 'Personal de apoyo en la recolección'],
        ];

        foreach ($funciones as $codigo => [$nombre, $descripcion]) {
            $tipo = TipoPersonal::firstWhere('code', $codigo) ?? new TipoPersonal(['name' => $nombre, 'description' => $descripcion]);
            $tipo->code = $codigo;
            $tipo->is_system = true;
            $tipo->save();
        }
    }
}
