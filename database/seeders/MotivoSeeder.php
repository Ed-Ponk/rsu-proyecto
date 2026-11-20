<?php

namespace Database\Seeders;

use App\Models\Motivo;
use Illuminate\Database\Seeder;

class MotivoSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            'Imprevistos' => 'Situaciones inesperadas que obligan a cambiar la programación.',
            'Reprogramación' => 'La programación se reorganiza por decisión de la jefatura.',
            'Personal faltante' => 'Una persona programada no se presentó o no puede trabajar ese día.',
            'Falla mecánica' => 'El vehículo programado no puede salir por avería o mantenimiento.',
            'Vacaciones o permiso' => 'La persona tiene vacaciones, licencia o permiso aprobado.',
            'Otros motivos' => 'Cualquier otra razón (se detalla en la descripción del cambio).',
        ];

        foreach ($motivos as $nombre => $descripcion) {
            Motivo::firstOrCreate(['name' => $nombre], ['description' => $descripcion]);
        }
    }
}
