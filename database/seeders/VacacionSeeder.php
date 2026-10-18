<?php

namespace Database\Seeders;

use App\Models\Personal;
use App\Models\Vacacion;
use Illuminate\Database\Seeder;

class VacacionSeeder extends Seeder
{
    /** Tres solicitudes de ejemplo (pendiente, aprobada y rechazada) para que el listado no aparezca vacío. */
    public function run(): void
    {
        // dni => [días, inicio desde hoy, estado, notas]
        $solicitudes = [
            '80000019' => [7, 20, Vacacion::PENDIENTE, 'Viaje familiar'],
            '12345678' => [10, 45, Vacacion::APROBADA, 'Vacaciones de fin de año'],
            '70000008' => [15, 10, Vacacion::RECHAZADA, 'Se necesita cubrir la ruta norte'],
        ];

        foreach ($solicitudes as $dni => [$dias, $desdeHoy, $estado, $notas]) {
            $persona = Personal::firstWhere('dni', $dni);

            if (! $persona) {
                continue;
            }

            $inicio = today()->addDays($desdeHoy);

            Vacacion::firstOrCreate(
                ['user_id' => $persona->id, 'notes' => $notas],
                [
                    'start_date' => $inicio->toDateString(),
                    'end_date' => Vacacion::fechaFin($inicio, $dias)->toDateString(),
                    'days' => $dias,
                    'status' => $estado,
                ],
            );
        }
    }
}
