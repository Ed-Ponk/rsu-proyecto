<?php

namespace Database\Seeders;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\Turno;
use Illuminate\Database\Seeder;

class PersonalSeeder extends Seeder
{
    public function run(): void
    {
        $conductor = TipoPersonal::conductor();
        $ayudante = TipoPersonal::ayudante();

        $equipo = [
            ['dni' => '12345678', 'name' => 'Carlos', 'last_name' => 'Guerrero', 'tipo' => $conductor, 'birthdate' => '1988-03-14',
                'license' => 'Q12345678', 'email' => 'carlos.guerrero@conductor.test', 'phone' => '987654321'],
            ['dni' => '70000008', 'name' => 'Fernando', 'last_name' => 'Ramírez', 'tipo' => $conductor, 'birthdate' => '1985-07-02',
                'license' => 'Q70000008', 'email' => 'fernando.ramirez@conductor.test', 'phone' => '987000008'],
            ['dni' => '80000019', 'name' => 'Miguel', 'last_name' => 'Gómez', 'tipo' => $ayudante, 'birthdate' => '1992-11-21',
                'license' => null, 'email' => 'miguel.gomez@ayudante.test', 'phone' => '987000019'],
            ['dni' => '70000007', 'name' => 'Juan', 'last_name' => 'Pérez', 'tipo' => $ayudante, 'birthdate' => '1990-05-30',
                'license' => null, 'email' => 'juan.perez@ayudante.test', 'phone' => '987000007'],
            ['dni' => '10000001', 'name' => 'Álvaro', 'last_name' => 'Ramírez', 'tipo' => $ayudante, 'birthdate' => '1995-01-09',
                'license' => null, 'email' => 'alvaro@ayudante.test', 'phone' => null],
            ['dni' => '10000002', 'name' => 'Raúl', 'last_name' => 'Quiroz Jiménez', 'tipo' => $ayudante, 'birthdate' => '1993-09-17',
                'license' => null, 'email' => 'raul.quiroz@ayudante.test', 'phone' => null],
        ];

        $porDni = [];

        foreach ($equipo as $fila) {
            $porDni[$fila['dni']] = Personal::updateOrCreate(
                ['dni' => $fila['dni']],
                [
                    'usertype_id' => $fila['tipo']->id,
                    'name' => $fila['name'],
                    'last_name' => $fila['last_name'],
                    'birthdate' => $fila['birthdate'],
                    'license' => $fila['license'],
                    'email' => $fila['email'],
                    'phone' => $fila['phone'],
                    'address' => 'Av. Principal 123, José Leonardo Ortiz, Chiclayo',
                    'password' => 'secret123',   // clave de marcación (se guarda cifrada)
                    'status' => true,
                ],
            );
        }

        // dni => [tipo, inicio, fin, salario, activo]
        $contratos = [
            '12345678' => [Contrato::PERMANENTE, '2026-02-02', null, 1800, true],
            '70000008' => [Contrato::PERMANENTE, '2021-03-16', null, 3351, true],
            '80000019' => [Contrato::NOMBRADO, '2024-04-16', null, 1963, true],
            '70000007' => [Contrato::TEMPORAL, '2025-12-18', '2026-02-18', 3370, false],   // vencido: puede recontratarse desde el 18/04/2026
        ];

        foreach ($contratos as $dni => [$tipo, $inicio, $fin, $salario, $activo]) {
            // whereDate: la fecha puede estar guardada con o sin hora, así que se compara solo el día.
            $yaExiste = Contrato::where('user_id', $porDni[$dni]->id)->whereDate('start_date', $inicio)->exists();

            if (! $yaExiste) {
                Contrato::create([
                    'user_id' => $porDni[$dni]->id, 'type' => $tipo, 'start_date' => $inicio,
                    'end_date' => $fin, 'salary' => $salario, 'is_active' => $activo,
                ]);
            }
        }

        // Asistencias de ayer para que el listado no aparezca vacío.
        $turnoManana = Turno::firstWhere('name', 'Mañana');
        $ayer = today()->subDay()->toDateString();
        $marcas = ['06:02:00' => Asistencia::ENTRADA, '14:03:00' => Asistencia::SALIDA];

        foreach (['12345678', '70000008', '80000019'] as $dni) {
            foreach ($marcas as $hora => $tipo) {
                $yaExiste = Asistencia::where('user_id', $porDni[$dni]->id)->whereDate('date', $ayer)->where('time', $hora)->exists();

                if (! $yaExiste) {
                    Asistencia::create([
                        'user_id' => $porDni[$dni]->id, 'date' => $ayer, 'time' => $hora,
                        'schedule_id' => $turnoManana?->id, 'shift_date' => $ayer,   // turno Mañana de ayer
                        'type' => $tipo, 'status' => Asistencia::PRESENTE, 'source' => 'admin',
                        'late_minutes' => $tipo === Asistencia::ENTRADA ? 0 : null,  // 06:02: dentro de la tolerancia
                    ]);
                }
            }
        }
    }
}
