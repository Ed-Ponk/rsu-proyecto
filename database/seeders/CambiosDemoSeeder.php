<?php

namespace Database\Seeders;

use App\Models\Contrato;
use App\Models\GrupoPersonal;
use App\Models\Motivo;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\User;
use App\Models\Vacacion;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Services\ReglasProgramacion;
use App\Services\ServicioCambios;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Datos de ejemplo del Avance 04 (solo desarrollo): más grupos que trabajan todos los días, programaciones desde hoy,
 * una vacación que deja incompleta una programación y cambios individuales y masivos con su motivo.
 * Se puede ejecutar varias veces: lo que ya existe se respeta.
 */
class CambiosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MotivoSeeder::class);

        $conductores = $this->personal('conductor', 5, '9100');
        $ayudantes = $this->personal('ayudante', 12, '9200');
        $zonas = Zona::where('status', true)->orderBy('id')->get();
        $vehiculos = Vehiculo::where('status', true)->orderBy('id')->get();
        $turnos = Turno::orderBy('time_start')->get();

        if ($zonas->count() < 4 || $vehiculos->count() < 4 || $turnos->count() < 3) {
            $this->command?->warn('Faltan zonas, vehículos o turnos para el ejemplo del Avance 04.');

            return;
        }

        // Grupos que trabajan los siete días: así siempre hay programaciones hoy y mañana para el dashboard.
        $definiciones = [
            ['Grupo C (diario)', 2, 2, 1, [1, 2, 3, 4, 5, 6, 7], 2, [0, 1]],
            ['Grupo D (diario)', 3, 3, 2, [1, 2, 3, 4, 5, 6, 7], 3, [2, 3]],
        ];

        foreach ($definiciones as [$nombre, $iZona, $iVehiculo, $iTurno, $dias, $iConductor, $iAyudantes]) {
            if (GrupoPersonal::where('name', $nombre)->exists()) {
                continue;
            }

            $grupo = GrupoPersonal::create([
                'name' => $nombre, 'shift_id' => $turnos[$iTurno]->id, 'zone_id' => $zonas[$iZona]->id, 'vehicle_id' => $vehiculos[$iVehiculo]->id,
                'weekdays' => $dias, 'driver_id' => $conductores[$iConductor]->id, 'status' => true,
            ]);

            foreach ($iAyudantes as $posicion => $indice) {
                $grupo->ayudantes()->attach($ayudantes[$indice]->id, ['position' => $posicion + 1]);
            }
        }

        $admin = User::orderBy('id')->first();
        $reglas = new ReglasProgramacion;

        foreach (GrupoPersonal::whereIn('name', ['Grupo C (diario)', 'Grupo D (diario)'])->with('ayudantes')->get() as $grupo) {
            if (Programacion::where('group_id', $grupo->id)->exists()) {
                continue;
            }

            try {
                $reglas->generar([[
                    'inicio' => today()->toDateString(), 'fin' => today()->addDays(13)->toDateString(), 'dias' => $grupo->dias_ordenados,
                    'turno_id' => $grupo->shift_id, 'zona_id' => $grupo->zone_id, 'vehiculo_id' => $grupo->vehicle_id, 'conductor_id' => $grupo->driver_id,
                    'ayudantes' => $grupo->ayudantes->pluck('id')->all(), 'grupo_id' => $grupo->id, 'etiqueta' => $grupo->name,
                ]], $admin?->id);
            } catch (ValidationException) {
                $this->command?->warn("No se pudo programar {$grupo->name} (probablemente choca con programaciones existentes).");
            }
        }

        $this->situaciones($conductores, $ayudantes, $admin?->id);
    }

    /** Vacación que deja incompleta una programación y cambios con su motivo (se hacen una sola vez). */
    private function situaciones($conductores, $ayudantes, ?int $adminId): void
    {
        $d = GrupoPersonal::firstWhere('name', 'Grupo D (diario)');
        $c = GrupoPersonal::firstWhere('name', 'Grupo C (diario)');

        if (! $c || ! $d || Vacacion::where('notes', 'Ejemplo Avance 04: vacaciones de hoy')->exists()) {
            return;
        }

        // El conductor de D tiene vacaciones aprobadas hoy: la programación de D queda incompleta en el dashboard.
        Vacacion::firstOrCreate(
            ['user_id' => $d->driver_id, 'notes' => 'Ejemplo Avance 04: vacaciones de hoy'],
            ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'days' => 1, 'status' => Vacacion::APROBADA],
        );

        // Y un ayudante de C también: así el dashboard muestra dos programaciones incompletas.
        Vacacion::firstOrCreate(
            ['user_id' => $c->ayudantes()->first()->id, 'notes' => 'Ejemplo Avance 04: ayudante de vacaciones hoy'],
            ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'days' => 1, 'status' => Vacacion::APROBADA],
        );

        $servicio = new ServicioCambios(new ReglasProgramacion);
        $imprevistos = Motivo::firstWhere('name', 'Imprevistos')?->id;
        $permiso = Motivo::firstWhere('name', 'Vacaciones o permiso')?->id;
        $reserva = $conductores[4];

        // Individual: el próximo día de C el conductor es otro.
        $manana = Programacion::where('group_id', $c->id)->where('date', '>', today()->toDateString())->orderBy('date')->first();   // el primer día futuro (mañana puede ser feriado)
        if ($manana) {
            $servicio->modificar($manana, [
                'vehiculo_id' => $manana->vehicle_id, 'conductor_id' => $reserva->id, 'ayudantes' => $manana->ayudantes()->pluck('users.id')->all(),
                'motivos' => ['conductor' => ['motivo_id' => $imprevistos, 'descripcion' => 'El conductor titular llegó tarde']],
            ], $adminId);
        }

        // Masivo: durante cuatro días pasados mañana, un ayudante de D es reemplazado por otro con permiso.
        $servicio->masivo([
            'rol' => 'ayudante', 'saliente_id' => $ayudantes[3]->id, 'entrante_id' => $ayudantes[9]->id, 'zona_id' => $d->zone_id,
            'desde' => today()->addDays(3)->toDateString(), 'hasta' => today()->addDays(6)->toDateString(),
            'motivo_id' => $permiso, 'descripcion' => 'Permiso por trámite personal',
        ], $adminId);
    }

    /** Crea (o reutiliza) personal de ejemplo con contrato vigente. @return list<Personal> */
    private function personal(string $funcion, int $cantidad, string $prefijo)
    {
        $tipo = $funcion === 'conductor' ? TipoPersonal::conductor() : TipoPersonal::ayudante();
        $lista = [];

        $nombres = ['Rosa', 'Luis', 'Marco', 'Elena', 'Pedro', 'Lucía', 'Jorge', 'Ana', 'Víctor', 'Carmen', 'Raúl', 'Julia'];
        $apellidos = ['Quispe', 'Torres', 'Flores', 'Rojas', 'Díaz', 'Castro', 'Vargas', 'Mendoza', 'Ramos', 'Silva', 'Paredes', 'Campos'];

        for ($i = 0; $i < $cantidad; $i++) {
            $dni = $prefijo.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $persona = Personal::firstWhere('dni', $dni) ?? Personal::create([
                'dni' => $dni, 'usertype_id' => $tipo->id, 'name' => $nombres[($i + ($funcion === 'conductor' ? 3 : 0)) % 12],
                'last_name' => $apellidos[($i * 5 + 1) % 12].' '.$apellidos[($i * 7 + 4) % 12], 'birthdate' => '1990-05-12',
                'email' => "demo{$dni}@rsu.test", 'address' => 'Av. Principal 123, Chiclayo', 'password' => Str::random(32), 'status' => true,
            ]);

            if (! $persona->contratos()->exists()) {
                Contrato::create(['user_id' => $persona->id, 'type' => Contrato::PERMANENTE, 'start_date' => today()->subYear()->toDateString(), 'salary' => 1500, 'is_active' => true]);
            }

            $lista[] = $persona;
        }

        return $lista;
    }
}
