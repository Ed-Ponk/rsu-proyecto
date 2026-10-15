<?php

namespace Database\Seeders;

use App\Models\Turno;
use Illuminate\Database\Seeder;

class TurnoSeeder extends Seeder
{
    public function run(): void
    {
        $turnos = [
            'Mañana' => ['06:00', '14:00', 'Primer turno del día'],
            'Tarde' => ['14:00', '22:00', 'Segundo turno del día'],
            'Noche' => ['22:00', '06:00', 'Turno nocturno: termina al día siguiente'],
        ];

        foreach ($turnos as $nombre => [$inicio, $fin, $descripcion]) {
            Turno::updateOrCreate(
                ['name' => $nombre],
                ['time_start' => $inicio, 'time_end' => $fin, 'description' => $descripcion],
            );
        }
    }
}
