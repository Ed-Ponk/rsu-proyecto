<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Bitacora;
use App\Models\Contrato;
use App\Models\Feriado;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\Vacacion;
use App\Models\Vehiculo;
use App\Models\Zona;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de la programación de rutas. Una "solicitud" es lo que se quiere generar para un grupo:
 *   inicio, fin, dias[1-7], turno_id, zona_id, vehiculo_id, conductor_id, ayudantes[ids], etiqueta?, ignorar_id?,
 *   excluir?[ids] (personas que no se deben sugerir como reemplazo)
 *
 * `revisar` no escribe nada: devuelve los errores, las sugerencias de reemplazo y las fechas que se generarían.
 * `generar` repite la revisión con las personas bloqueadas y recién entonces guarda, así dos usuarios que
 * programen a la vez no pueden dejar a la misma persona o vehículo en dos lugares.
 */
class ReglasProgramacion
{
    public const MAX_DIAS_RANGO = 366;

    /** Tope de programaciones que se generan de una sola vez (evita pedidos enormes por error); se guardan por lotes. */
    public const MAX_PROGRAMACIONES = 5000;

    /** Fechas del rango (ambas incluidas) que caen en alguno de los días de la semana elegidos (1 = lunes ... 7 = domingo). */
    public function fechas(Carbon $inicio, Carbon $fin, array $dias): array
    {
        $fechas = [];

        for ($dia = $inicio->copy(); $dia->lte($fin); $dia->addDay()) {
            if (in_array($dia->dayOfWeekIso, $dias, true)) {
                $fechas[] = $dia->toDateString();
            }
        }

        return $fechas;
    }

    /** "01/04/2026, 03/04/2026, 05/04/2026 y 4 días más" */
    public static function resumenDeFechas(array $fechas, int $mostrar = 3): string
    {
        $formateadas = array_map(fn (string $f) => Carbon::parse($f)->format('d/m/Y'), array_values($fechas));
        $resto = count($formateadas) - $mostrar;

        if ($resto <= 0) {
            return count($formateadas) > 1
                ? implode(', ', array_slice($formateadas, 0, -1)).' y '.end($formateadas)
                : ($formateadas[0] ?? '');
        }

        return implode(', ', array_slice($formateadas, 0, $mostrar)).' y '.$resto.' '.($resto === 1 ? 'día más' : 'días más');
    }

    /**
     * @param  array<int, array>  $solicitudes
     * @return array{ok: bool, errores: list<string>, sugerencias: list<string>, avisos: list<string>, plan: list<array>}
     */
    public function revisar(array $solicitudes): array
    {
        $this->horasDeTurnos = null;
        $errores = $sugerencias = $avisos = $plan = [];
        $ocupado = [];   // lo que ya tomaron los grupos anteriores del mismo lote
        $varios = count($solicitudes) > 1;

        foreach ($solicitudes as $solicitud) {
            $prefijo = $varios && filled($solicitud['etiqueta'] ?? null) ? '['.$solicitud['etiqueta'].'] ' : '';
            $resultado = $this->revisarUna($solicitud, $ocupado);

            foreach ($resultado['errores'] as $texto) {
                // Los errores de fechas valen para todos los grupos: se dicen una sola vez y sin prefijo.
                if (str_starts_with($texto, 'La fecha de') || str_starts_with($texto, 'El rango no puede') || str_starts_with($texto, 'Indique la fecha')) {
                    $errores[] = $texto;

                    continue;
                }

                $errores[] = $prefijo.$texto;
            }
            foreach ($resultado['sugerencias'] as $texto) {
                $sugerencias[] = $prefijo.$texto;
            }
            foreach ($resultado['avisos'] as $texto) {
                $avisos[] = $prefijo.$texto;
            }

            $plan[] = ['etiqueta' => $solicitud['etiqueta'] ?? null, 'fechas' => $resultado['fechas']] + $solicitud;
        }

        $errores = array_values(array_unique($errores));
        $avisos = array_values(array_unique($avisos));
        $total = array_sum(array_map(fn (array $p) => count($p['fechas']), $plan));

        if ($errores === [] && $total === 0) {
            $errores[] = 'No hay nada que generar: ningún día del rango coincide con los días de trabajo elegidos.';
        }

        if ($total > self::MAX_PROGRAMACIONES) {
            $errores[] = "Se generarían {$total} programaciones y el máximo por vez es ".self::MAX_PROGRAMACIONES.'. Reduzca el rango de fechas o la cantidad de grupos.';
        }

        return ['ok' => $errores === [], 'errores' => $errores, 'sugerencias' => $sugerencias, 'avisos' => $avisos, 'plan' => $plan];
    }

    /**
     * Guarda las programaciones de todas las solicitudes (todo o nada).
     *
     * @return int cantidad de programaciones creadas
     *
     * @throws ValidationException
     */
    public function generar(array $solicitudes, ?int $porUsuario = null): int
    {
        return $this->sinChoques(fn () => DB::transaction(function () use ($solicitudes, $porUsuario) {
            $personas = collect($solicitudes)
                ->flatMap(fn (array $s) => [$s['conductor_id'] ?? null, ...($s['ayudantes'] ?? [])])
                ->filter()->unique()->sort()->values();

            $this->bloquear($personas->all(), collect($solicitudes)->pluck('vehiculo_id')->filter()->unique()->all());

            $revision = $this->revisar($solicitudes);

            if (! $revision['ok']) {
                throw ValidationException::withMessages(['programacion' => $revision['errores']]);
            }

            $ahora = now();
            $filas = [];

            foreach ($revision['plan'] as $solicitud) {
                foreach ($solicitud['fechas'] as $fecha) {
                    $filas[] = [
                        'fila' => [
                            'date' => $fecha, 'group_id' => $solicitud['grupo_id'] ?? null, 'zone_id' => $solicitud['zona_id'], 'shift_id' => $solicitud['turno_id'],
                            'vehicle_id' => $solicitud['vehiculo_id'], 'driver_id' => $solicitud['conductor_id'], 'status' => Programacion::PROGRAMADO,
                            'notes' => filled($solicitud['notas'] ?? null) ? trim($solicitud['notas']) : null, 'created_by' => $porUsuario,
                        ],
                        'ayudantes' => array_values($solicitud['ayudantes']),
                    ];
                }
            }

            // Inserciones por lotes: miles de programaciones en pocas consultas (una por fila haría decenas de miles).
            foreach (array_chunk($filas, 80) as $lote) {
                DB::table('programmings')->insert(array_map(fn (array $f) => $f['fila'] + ['created_at' => $ahora, 'updated_at' => $ahora], $lote));

                // Los ids se recuperan por la clave única (fecha, turno, vehículo) que ya garantiza la base de datos.
                $ids = Programacion::whereIn('date', array_unique(array_column(array_column($lote, 'fila'), 'date')))
                    ->whereIn('vehicle_id', array_unique(array_column(array_column($lote, 'fila'), 'vehicle_id')))
                    ->whereIn('shift_id', array_unique(array_column(array_column($lote, 'fila'), 'shift_id')))
                    ->get(['id', 'date', 'shift_id', 'vehicle_id'])
                    ->mapWithKeys(fn (Programacion $p) => [$p->date->toDateString().'|'.$p->shift_id.'|'.$p->vehicle_id => $p->id]);

                $ayudantes = [];
                $bitacora = [];

                foreach ($lote as $f) {
                    $id = $ids[$f['fila']['date'].'|'.$f['fila']['shift_id'].'|'.$f['fila']['vehicle_id']];

                    foreach ($f['ayudantes'] as $posicion => $idAyudante) {
                        $ayudantes[] = ['programming_id' => $id, 'user_id' => $idAyudante, 'date' => $f['fila']['date'], 'shift_id' => $f['fila']['shift_id'], 'position' => $posicion + 1];
                    }

                    $bitacora[] = ['user_id' => $porUsuario, 'action' => 'creado', 'model' => 'Programacion', 'model_id' => $id, 'before' => null,
                        'after' => json_encode($f['fila'], JSON_UNESCAPED_UNICODE), 'created_at' => $ahora];
                }

                DB::table('programming_helpers')->insert($ayudantes);
                DB::table('audit_logs')->insert($bitacora);
            }

            $creadas = count($filas);

            return $creadas;
        }, 3));
    }

    /**
     * Bloquea a las personas y a los vehículos implicados, siempre en el mismo orden (por id) para que dos procesos no se
     * bloqueen entre sí. Sin bloquear también los vehículos, dos grupos sin personas en común podían competir por el mismo.
     *
     * @param  list<int>  $personas
     * @param  list<int>  $vehiculos
     */
    private function bloquear(array $personas, array $vehiculos): void
    {
        Personal::whereIn('users.id', $personas)->orderBy('users.id')->lockForUpdate()->get(['users.id']);
        Vehiculo::whereIn('id', $vehiculos)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /**
     * Si a pesar de todo la base de datos detecta un duplicado (otro usuario guardó lo mismo en el mismo instante),
     * el usuario recibe un mensaje claro y no un error 500.
     */
    private function sinChoques(callable $operacion): mixed
    {
        try {
            return $operacion();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['programacion' => ['Otro usuario acaba de programar a esa persona o ese vehículo en la misma fecha y turno. Vuelva a validar la disponibilidad.']]);
        }
    }

    /**
     * Cambia el conductor, los ayudantes, el vehículo y las observaciones de una programación (solo si sigue "programado").
     * Se vuelven a aplicar todas las reglas; el cambio de ayudantes queda en el historial.
     *
     * @throws ValidationException
     */
    public function actualizar(Programacion $programacion, array $datos): void
    {
        $this->sinChoques(fn () => DB::transaction(function () use ($programacion, $datos) {
            $programacion = Programacion::lockForUpdate()->findOrFail($programacion->id);

            if (! $programacion->esProgramado()) {
                throw ValidationException::withMessages(['programacion' => ['Esta programación ya fue finalizada y no se puede modificar.']]);
            }

            if ($programacion->date->lt(today())) {
                throw ValidationException::withMessages(['programacion' => ['Esta programación ya pasó su fecha: solo se puede finalizar o eliminar.']]);
            }

            $personas = collect([$datos['conductor_id'], ...$datos['ayudantes']])->filter()->unique()->sort()->values();
            $this->bloquear($personas->all(), [(int) $datos['vehiculo_id']]);

            // Quien ya registró asistencia ese día está trabajando: no se lo puede quitar de la programación.
            $nuevos = array_map('intval', [$datos['conductor_id'], ...$datos['ayudantes']]);
            $quitados = $programacion->personasConAsistencia()->reject(fn (Personal $p) => in_array($p->id, $nuevos, true));
            if ($quitados->isNotEmpty()) {
                throw ValidationException::withMessages(['programacion' => [$quitados->map->nombre_completo->implode(', ').' ya registró asistencia ese día y no se puede quitar de la programación.']]);
            }

            $revision = $this->revisar([[
                'inicio' => $programacion->date->toDateString(),
                'fin' => $programacion->date->toDateString(),
                'dias' => [$programacion->date->dayOfWeekIso],
                'turno_id' => $programacion->shift_id,
                'zona_id' => $programacion->zone_id,
                'vehiculo_id' => $datos['vehiculo_id'],
                'conductor_id' => $datos['conductor_id'],
                'ayudantes' => $datos['ayudantes'],
                'ignorar_id' => $programacion->id,
            ]]);

            if (! $revision['ok']) {
                throw ValidationException::withMessages(['programacion' => $revision['errores']]);
            }

            $antes = $programacion->ayudantes()->get()->map->nombre_completo->implode(', ');

            $programacion->update([
                'vehicle_id' => $datos['vehiculo_id'],
                'driver_id' => $datos['conductor_id'],
                'notes' => filled($datos['notas'] ?? null) ? trim($datos['notas']) : null,
            ]);

            $ids = array_map('intval', $datos['ayudantes']);
            $actuales = $programacion->ayudantes()->pluck('users.id')->map(fn ($i) => (int) $i)->all();

            if ($ids !== $actuales) {
                $programacion->asignarAyudantes($ids);
                $despues = $programacion->ayudantes()->get()->map->nombre_completo->implode(', ');
                Bitacora::registrar('modificado', $programacion, ['ayudantes' => $antes], ['ayudantes' => $despues]);
            }
        }, 3));
    }

    /** @return array{errores: list<string>, sugerencias: list<string>, avisos: list<string>, fechas: list<string>} */
    private function revisarUna(array $s, array &$ocupado): array
    {
        $errores = $avisos = [];

        $ignorarIds = array_values(array_filter(array_map('intval', (array) ($s['ignorar_ids'] ?? $s['ignorar_id'] ?? []))));
        $ignorar = $ignorarIds !== [];

        // Fechas explícitas (reemplazos): se usan tal cual, sin rango ni feriados.
        if (isset($s['fechas'])) {
            $fechas = array_values(array_unique($s['fechas']));
            sort($fechas);

            return $this->revisarRecursos($s, $fechas, $ignorarIds, $ocupado, $errores, $avisos);
        }

        $inicio = $this->fecha($s['inicio'] ?? null);
        $fin = $this->fecha($s['fin'] ?? null);
        $dias = array_values(array_unique(array_filter(array_map('intval', (array) ($s['dias'] ?? [])), fn ($d) => $d >= 1 && $d <= 7)));
        $sinFechas = fn (array $e, array $a) => ['errores' => $e, 'sugerencias' => [], 'avisos' => $a, 'fechas' => []];

        if (! $inicio || ! $fin) {
            return $sinFechas(['Indique la fecha de inicio y la fecha de fin.'], $avisos);
        }

        if ($fin->lt($inicio)) {
            return $sinFechas(['La fecha de fin no puede ser anterior a la fecha de inicio.'], $avisos);
        }

        if (! $ignorar && $inicio->lt(today())) {
            $errores[] = 'La fecha de inicio no puede ser anterior a hoy.';
        }

        if ($inicio->diffInDays($fin) + 1 > self::MAX_DIAS_RANGO) {
            return $sinFechas([...$errores, 'El rango no puede superar '.self::MAX_DIAS_RANGO.' días.'], $avisos);
        }

        if ($dias === []) {
            return $sinFechas([...$errores, 'Seleccione al menos un día de trabajo.'], $avisos);
        }

        // Los feriados activos no se trabajan: se omiten y se avisa. Los inactivos no cuentan.
        $fechas = $this->fechas($inicio, $fin, $dias);
        $feriados = $ignorar
            ? collect()
            : Feriado::activo()->entre($inicio->toDateString(), $fin->toDateString())->get()->keyBy(fn (Feriado $f) => $f->date->toDateString());
        $omitidas = array_values(array_filter($fechas, fn (string $f) => $feriados->has($f)));
        $fechas = array_values(array_diff($fechas, $omitidas));

        if ($omitidas) {
            $avisos[] = 'Se omitirán los feriados: '.implode(', ', array_map(
                fn (string $f) => Carbon::parse($f)->format('d/m/Y').' ('.$feriados[$f]->description.')',
                array_slice($omitidas, 0, 3),
            )).(count($omitidas) > 3 ? ' y '.(count($omitidas) - 3).' más' : '').'.';
        }

        if ($fechas === []) {
            return $sinFechas([...$errores, 'El rango no incluye ningún día de trabajo: los días elegidos no caen en el rango o son feriados.'], $avisos);
        }

        return $this->revisarRecursos($s, $fechas, $ignorarIds, $ocupado, $errores, $avisos);
    }

    /**
     * Revisa turno, zona, vehículo y personas para las fechas ya decididas.
     *
     * @return array{errores: list<string>, sugerencias: list<string>, avisos: list<string>, fechas: list<string>}
     */
    private function revisarRecursos(array $s, array $fechas, array $ignorarIds, array &$ocupado, array $errores, array $avisos): array
    {
        $sugerencias = [];
        $turno = Turno::find($s['turno_id'] ?? null);
        $zona = Zona::find($s['zona_id'] ?? null);
        $vehiculo = Vehiculo::find($s['vehiculo_id'] ?? null);

        if (! $turno) {
            $errores[] = 'Seleccione un turno válido.';
        }

        if (! $zona) {
            $errores[] = 'Seleccione una zona válida.';
        } elseif (! $zona->status) {
            $errores[] = "La zona «{$zona->name}» está inactiva.";
        }

        if (! $vehiculo) {
            $errores[] = 'Seleccione un vehículo válido.';
        } elseif (! $vehiculo->status) {
            $errores[] = "El vehículo {$vehiculo->name} ({$vehiculo->plate}) está inactivo.";
        }

        $idConductor = (int) ($s['conductor_id'] ?? 0);
        $idsAyudantes = array_values(array_filter(array_map('intval', (array) ($s['ayudantes'] ?? []))));
        $todos = array_values(array_filter([$idConductor, ...$idsAyudantes]));

        if (count($todos) !== count(array_unique($todos))) {
            $errores[] = 'Una misma persona no puede ocupar dos puestos en la programación.';
        }

        if (! $idConductor) {
            $errores[] = 'Seleccione al conductor.';
        }

        if (! $idsAyudantes) {
            $errores[] = 'Seleccione al menos un ayudante.';
        }

        if ($vehiculo && 1 + count($idsAyudantes) > $vehiculo->occupant_capacity) {
            $errores[] = "El vehículo {$vehiculo->name} tiene capacidad para {$vehiculo->occupant_capacity} persona(s) (1 conductor y "
                .max(0, $vehiculo->occupant_capacity - 1).' ayudante(s)).';
        }

        $personas = Personal::with('tipo')->whereIn('users.id', $todos)->get()->keyBy('id');

        foreach ($todos as $id) {
            if (! $personas->has($id)) {
                $errores[] = 'Una de las personas elegidas ya no existe.';
            }
        }

        $conductor = $personas->get($idConductor);
        if ($conductor && $conductor->usertype_id !== TipoPersonal::conductor()?->id) {
            $errores[] = "{$conductor->nombre_completo}: no es conductor.";
        }

        foreach ($idsAyudantes as $id) {
            $ayudante = $personas->get($id);
            if ($ayudante && $ayudante->usertype_id !== TipoPersonal::ayudante()?->id) {
                $errores[] = "{$ayudante->nombre_completo}: no es ayudante.";
            }
        }

        // Lo más común al empezar: volver a programar días que el grupo ya tiene. Se dice primero y en claro, porque
        // si no, cada persona y el vehículo aparecen "ocupados" sin que se entienda por qué.
        if (filled($s['grupo_id'] ?? null)) {
            $repetidas = Programacion::where('group_id', $s['grupo_id'])->whereIn('date', $fechas)
                ->when($ignorarIds, fn ($q) => $q->whereNotIn('id', $ignorarIds))
                ->orderBy('date')->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->values()->all();

            if ($repetidas) {
                $nombre = $s['etiqueta'] ?? 'El grupo';
                $mensaje = count($repetidas) === count($fechas)
                    ? "{$nombre} ya está programado en todos esos días (".self::resumenDeFechas($repetidas).'). Ya aparecen en la lista de programaciones: no hace falta volver a generarlos.'
                    : "{$nombre} ya está programado en ".count($repetidas).' de esos días ('.self::resumenDeFechas($repetidas).').';

                if (count($repetidas) === count($fechas)) {
                    return ['errores' => [...$errores, $mensaje], 'avisos' => $avisos, 'fechas' => $fechas, 'sugerencias' => [
                        'Para ver esos días, filtre la lista de programaciones por esas fechas.',
                        'Para cambiar al conductor, a un ayudante o el vehículo en esos días, use «Reemplazar».',
                    ]];
                }

                $errores[] = $mensaje;
                $sugerencias[] = 'Empiece el rango después del '.Carbon::parse(end($repetidas))->format('d/m/Y').' para programar solo los días que faltan, o use «Reemplazar» para cambiar personas en los días ya programados.';
            }
        }

        if ($turno && $personas->isNotEmpty()) {
            [$mensajes, $malas, $avisosPersonas] = $this->disponibilidad($personas, $fechas, $turno->id, $vehiculo, $ignorarIds, $ocupado);
            $errores = [...$errores, ...$mensajes];
            $avisos = [...$avisos, ...$avisosPersonas];
            // Un reemplazo sugerido se reserva: no se ofrece a la misma persona para dos ausentes. Tampoco se sugiere a
            // quien se está sacando de la programación (reemplazos).
            $reservados = [...$todos, ...array_map('intval', (array) ($s['excluir'] ?? []))];

            foreach ($malas as $id => $fechasMalas) {
                $persona = $personas[$id];
                $reemplazo = $persona->status ? $this->buscarReemplazo($persona, $fechasMalas, $turno->id, $reservados, $ignorarIds, $ocupado) : null;

                if ($reemplazo) {
                    $reservados[] = $reemplazo->id;
                }

                $funcion = Str::lower($persona->tipo?->name ?? 'persona');

                $sugerencias[] = $reemplazo
                    ? "Cambie a {$persona->nombre_completo} por {$reemplazo->nombre_completo}: es {$funcion} y está libre en esos días."
                    : "No hay otro {$funcion} libre para cubrir a {$persona->nombre_completo} en esos días: los demás ya están programados, de vacaciones o sin contrato vigente.";
            }
        }

        return ['errores' => $errores, 'sugerencias' => $sugerencias, 'avisos' => $avisos, 'fechas' => $fechas];
    }

    /* ------------------------------------------------------------------ horarios entre turnos */

    /** @var array<int, array{0: int, 1: int}>|null minutos de inicio y fin de cada turno */
    private ?array $horasDeTurnos = null;

    private function horasDelTurno(int $turnoId): array
    {
        $this->horasDeTurnos ??= Turno::all()->mapWithKeys(fn (Turno $t) => [$t->id => [
            ((int) substr($t->time_start, 0, 2)) * 60 + (int) substr($t->time_start, 3, 2),
            ((int) substr($t->time_end, 0, 2)) * 60 + (int) substr($t->time_end, 3, 2),
        ]])->all();

        return $this->horasDeTurnos[$turnoId] ?? [0, 0];
    }

    /** Ventana de trabajo de un turno en una fecha: los nocturnos terminan al día siguiente. @return array{0: Carbon, 1: Carbon} */
    private function ventana(string $fecha, int $turnoId): array
    {
        [$inicio, $fin] = $this->horasDelTurno($turnoId);
        $dia = Carbon::parse($fecha)->startOfDay();

        return [$dia->copy()->addMinutes($inicio), $dia->copy()->addMinutes($fin <= $inicio ? $fin + 1440 : $fin)];
    }

    /**
     * Cómo choca un turno con otro de la misma persona:
     *   'igual'    = el mismo turno el mismo día,
     *   'dia'      = otro turno el mismo día (más de un turno por día),
     *   'descanso' = turnos de días distintos con menos descanso del mínimo (p. ej. noche y luego mañana),
     *   null       = no chocan.
     */
    private function choqueDeHorario(string $fecha, int $turnoId, string $otraFecha, int $otroTurno): ?string
    {
        if ($fecha === $otraFecha) {
            return $turnoId === $otroTurno ? 'igual' : 'dia';
        }

        [$aInicio, $aFin] = $this->ventana($fecha, $turnoId);
        [$bInicio, $bFin] = $this->ventana($otraFecha, $otroTurno);
        $minimo = (int) config('proyecto.programacion.descanso_minimo_horas', 8) * 60;

        $hueco = $aInicio->gte($bFin) ? abs($bFin->diffInMinutes($aInicio)) : ($bInicio->gte($aFin) ? abs($aFin->diffInMinutes($bInicio)) : -1);

        return $hueco < $minimo ? 'descanso' : null;
    }

    /**
     * Turnos ya programados de cada persona (cualquier turno, con un día de margen a cada lado para los descansos).
     *
     * @return array<int, list<array{0: string, 1: int}>> persona => [[fecha, turno], ...]
     */
    private function programadasDe(array $ids, string $desde, string $hasta, array $ignorarIds): array
    {
        $ini = Carbon::parse($desde)->subDay()->toDateString();
        $fin = Carbon::parse($hasta)->addDay()->toDateString();
        $res = [];

        foreach (Programacion::entre($ini, $fin)->when($ignorarIds, fn ($q) => $q->whereNotIn('id', $ignorarIds))->whereIn('driver_id', $ids)->get(['date', 'shift_id', 'driver_id']) as $f) {
            $res[$f->driver_id][] = [$f->date->toDateString(), (int) $f->shift_id];
        }

        foreach (DB::table('programming_helpers')->whereBetween('date', [$ini, $fin])->when($ignorarIds, fn ($q) => $q->whereNotIn('programming_id', $ignorarIds))
            ->whereIn('user_id', $ids)->get(['date', 'shift_id', 'user_id']) as $f) {
            $res[$f->user_id][] = [substr((string) $f->date, 0, 10), (int) $f->shift_id];
        }

        return $res;
    }

    /**
     * Revisa a cada persona (y al vehículo) en todas las fechas con pocas consultas.
     *
     * @param  Collection<int, Personal>  $personas
     * @return array{0: list<string>, 1: array<int, list<string>>, 2: list<string>} mensajes, por persona las fechas con problema, y avisos que no bloquean
     */
    private function disponibilidad(Collection $personas, array $fechas, int $turnoId, ?Vehiculo $vehiculo, array $ignorarIds, array &$ocupado): array
    {
        $mensajes = [];
        $avisos = [];
        $malas = [];
        $ids = $personas->keys()->all();
        $desde = $fechas[0];
        $hasta = end($fechas);
        $descanso = (int) config('proyecto.programacion.descanso_minimo_horas', 8);
        $nombresDeTurnos = Turno::pluck('name', 'id')->all();

        $contratos = Contrato::where('is_active', true)->whereIn('user_id', $ids)
            ->where('start_date', '<=', $hasta)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $desde))
            ->get()->groupBy('user_id');

        $vacaciones = Vacacion::where('status', Vacacion::APROBADA)->whereIn('user_id', $ids)
            ->queSeCruzaCon(Carbon::parse($desde), Carbon::parse($hasta))
            ->get()->groupBy('user_id');

        $existentes = $this->programadasDe($ids, $desde, $hasta, $ignorarIds);

        // Faltas recientes: no bloquean, pero conviene saberlo al programar.
        $diasFaltas = (int) config('proyecto.programacion.faltas_dias', 30);
        $faltas = Asistencia::where('status', Asistencia::AUSENTE)->whereIn('user_id', $ids)
            ->where('date', '>=', today()->subDays($diasFaltas)->toDateString())
            ->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        foreach ($personas as $id => $persona) {
            $porContrato = $porVacaciones = $porOcupacion = $porLote = $porDia = $porDescanso = [];
            $choque = [];   // primer turno con el que choca, para explicar el porqué: ['dia' => [fecha, turno], 'descanso' => ...]

            foreach ($fechas as $fecha) {
                if (! ($contratos[$id] ?? collect())->contains(fn (Contrato $c) => $c->start_date->toDateString() <= $fecha
                    && ($c->end_date === null || $c->end_date->toDateString() >= $fecha))) {
                    $porContrato[] = $fecha;
                }

                if (($vacaciones[$id] ?? collect())->contains(fn (Vacacion $v) => $v->start_date->toDateString() <= $fecha && $v->end_date->toDateString() >= $fecha)) {
                    $porVacaciones[] = $fecha;
                }

                foreach ($existentes[$id] ?? [] as [$otraFecha, $otroTurno]) {
                    $tipo = $this->choqueDeHorario($fecha, $turnoId, $otraFecha, $otroTurno);
                    match ($tipo) {
                        'igual' => $porOcupacion[] = $fecha,
                        'dia' => $porDia[] = $fecha,
                        'descanso' => $porDescanso[] = $fecha,
                        default => null,
                    };
                    if ($tipo) {
                        $choque[$tipo] ??= [$otraFecha, $otroTurno];
                    }
                }

                foreach ($ocupado['int'][$id] ?? [] as [$otraFecha, $otroTurno]) {
                    $tipo = $this->choqueDeHorario($fecha, $turnoId, $otraFecha, $otroTurno);
                    match ($tipo) {
                        'igual' => $porLote[] = $fecha,
                        'dia' => $porDia[] = $fecha,
                        'descanso' => $porDescanso[] = $fecha,
                        default => null,
                    };
                    if ($tipo && $tipo !== 'igual') {
                        $choque[$tipo] ??= [$otraFecha, $otroTurno];
                    }
                }

                $ocupado['int'][$id][] = [$fecha, $turnoId];
            }

            if (! $persona->status) {
                $mensajes[] = "{$persona->nombre_completo}: el empleado está inactivo.";
                $malas[$id] = $fechas;
            }

            // Si el contrato existe pero termina dentro del rango, se dice cuándo vence en vez de un mensaje genérico.
            $vence = ($contratos[$id] ?? collect())->filter(fn (Contrato $c) => $c->end_date)->map(fn (Contrato $c) => $c->end_date->toDateString())->max();
            $textoContrato = $porContrato && $vence && $vence >= $desde && $vence < end($porContrato)
                ? 'su contrato vence el '.Carbon::parse($vence)->format('d/m/Y').' y no cubre'
                : 'no tiene contrato vigente para';

            $turnoDe = fn (string $tipo) => isset($choque[$tipo])
                ? ' (ya tiene turno '.($nombresDeTurnos[$choque[$tipo][1]] ?? '').' el '.Carbon::parse($choque[$tipo][0])->format('d/m/Y').')'
                : '';

            $detalles = [
                [$porVacaciones, 'está de vacaciones aprobadas en'],
                [$porContrato, $textoContrato],
                [array_values(array_unique($porOcupacion)), 'ya está programado en este mismo turno en'],
                [array_values(array_unique($porLote)), 'está en dos de los grupos elegidos, en el mismo turno, en'],
                [array_values(array_unique($porDia)), 'ya trabaja otro turno'.$turnoDe('dia').' en'],
                [array_values(array_unique($porDescanso)), "no llega a descansar {$descanso} h entre turnos".$turnoDe('descanso').' en'],
            ];

            foreach ($detalles as [$afectadas, $texto]) {
                if ($afectadas) {
                    sort($afectadas);
                    $mensajes[] = "{$persona->nombre_completo} {$texto} ".self::resumenDeFechas($afectadas).'.';
                    $malas[$id] = array_values(array_unique([...($malas[$id] ?? []), ...$afectadas]));
                }
            }

            if (($faltas[$id] ?? 0) >= (int) config('proyecto.programacion.faltas_alerta', 3)) {
                $avisos[] = "{$persona->nombre_completo} tiene {$faltas[$id]} falta(s) en los últimos {$diasFaltas} días.";
            }

            $limite = Carbon::parse($hasta)->addDays((int) config('proyecto.programacion.contrato_por_vencer_dias', 15))->toDateString();
            if ($vence && $vence >= $hasta && $vence <= $limite && ! $porContrato) {
                $avisos[] = "El contrato de {$persona->nombre_completo} vence el ".Carbon::parse($vence)->format('d/m/Y').', pocos días después del último día programado.';
            }
        }

        if ($vehiculo) {
            $enUso = Programacion::entre($desde, $hasta)->where('shift_id', $turnoId)->where('vehicle_id', $vehiculo->id)
                ->when($ignorarIds, fn ($q) => $q->whereNotIn('id', $ignorarIds))->get(['date']);
            $afectadas = $enUso->map(fn ($p) => $p->date->toDateString())->all();

            foreach ($fechas as $fecha) {
                if (isset($ocupado[$fecha.'|'.$turnoId]['v'][$vehiculo->id])) {
                    $afectadas[] = $fecha;
                }
                $ocupado[$fecha.'|'.$turnoId]['v'][$vehiculo->id] = true;
            }

            $afectadas = array_values(array_unique(array_intersect($afectadas, $fechas)));
            sort($afectadas);

            if ($afectadas) {
                $mensajes[] = "El vehículo {$vehiculo->name} ({$vehiculo->plate}) ya sale en otra programación de este mismo turno en ".self::resumenDeFechas($afectadas).'.';
            }
        }

        return [$mensajes, $malas, $avisos];
    }

    /** Primera persona de la misma función que pueda cubrir todas las fechas con problema. */
    private function buscarReemplazo(Personal $persona, array $fechasMalas, int $turnoId, array $excluir, array $ignorarIds, array $ocupado): ?Personal
    {
        sort($fechasMalas);
        $desde = $fechasMalas[0];
        $hasta = end($fechasMalas);

        $candidatos = Personal::activos()->where('users.usertype_id', $persona->usertype_id)
            ->whereNotIn('users.id', $excluir)
            ->with([
                'contratos' => fn ($q) => $q->where('is_active', true),
                'vacaciones' => fn ($q) => $q->where('status', Vacacion::APROBADA)->queSeCruzaCon(Carbon::parse($desde), Carbon::parse($hasta)),
            ])
            ->orderBy('users.last_name')->orderBy('users.name')->limit(60)->get();

        if ($candidatos->isEmpty()) {
            return null;
        }

        $existentes = $this->programadasDe($candidatos->pluck('id')->all(), $desde, $hasta, $ignorarIds);

        return $candidatos->first(function (Personal $c) use ($fechasMalas, $existentes, $ocupado, $turnoId) {
            foreach ($fechasMalas as $fecha) {
                $conContrato = $c->contratos->contains(fn (Contrato $k) => $k->start_date->toDateString() <= $fecha
                    && ($k->end_date === null || $k->end_date->toDateString() >= $fecha));
                $deVacaciones = $c->vacaciones->contains(fn (Vacacion $v) => $v->start_date->toDateString() <= $fecha && $v->end_date->toDateString() >= $fecha);

                if (! $conContrato || $deVacaciones) {
                    return false;
                }

                foreach ([...($existentes[$c->id] ?? []), ...($ocupado['int'][$c->id] ?? [])] as [$otraFecha, $otroTurno]) {
                    if ($this->choqueDeHorario($fecha, $turnoId, $otraFecha, $otroTurno) !== null) {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    /* ------------------------------------------------------------------ reemplazo masivo */

    /** Programaciones pendientes (desde hoy) afectadas por un reemplazo. */
    private function afectadasPorReemplazo(array $d)
    {
        $desde = max($d['desde'], today()->toDateString());

        return Programacion::with('ayudantes')->where('status', Programacion::PROGRAMADO)
            ->entre($desde, $d['hasta'])
            ->when($d['rol'] === 'conductor', fn ($q) => $q->where('driver_id', $d['saliente_id']))
            ->when($d['rol'] === 'vehiculo', fn ($q) => $q->where('vehicle_id', $d['saliente_id']))
            ->when($d['rol'] === 'ayudante', fn ($q) => $q->whereHas('ayudantes', fn ($a) => $a->where('users.id', $d['saliente_id'])))
            ->orderBy('date')->get();
    }

    /**
     * Revisa un reemplazo (conductor, ayudante o vehículo) en las programaciones pendientes de un rango, sin guardar nada.
     *
     * @param  array{rol: string, saliente_id: int, entrante_id: int, desde: string, hasta: string}  $d
     * @return array{ok: bool, errores: list<string>, sugerencias: list<string>, avisos: list<string>, total: int, filas: Collection}
     */
    public function revisarReemplazo(array $d): array
    {
        $this->horasDeTurnos = null;

        if ((int) $d['saliente_id'] === (int) $d['entrante_id']) {
            return ['ok' => false, 'errores' => ['Quien sale y quien entra no pueden ser el mismo.'], 'sugerencias' => [], 'avisos' => [], 'total' => 0, 'filas' => collect()];
        }

        $filas = $this->afectadasPorReemplazo($d);

        if ($filas->isEmpty()) {
            return ['ok' => false, 'errores' => ['No hay programaciones pendientes con esa persona o vehículo en el rango elegido.'], 'sugerencias' => [], 'avisos' => [], 'total' => 0, 'filas' => $filas];
        }

        // Quien ya registró asistencia ese día está trabajando: se reemplaza desde el día siguiente.
        if ($d['rol'] !== 'vehiculo') {
            $yaTrabajo = Asistencia::where('status', Asistencia::PRESENTE)->where('user_id', $d['saliente_id'])
                ->whereIn('shift_date', $filas->map(fn (Programacion $p) => $p->date->toDateString())->all())
                ->orderBy('shift_date')->get(['shift_date'])->map(fn (Asistencia $a) => $a->shift_date->toDateString())->all();

            if ($yaTrabajo) {
                $nombre = Personal::find($d['saliente_id'])?->nombre_completo ?? 'La persona';

                return ['ok' => false, 'errores' => ["{$nombre} ya registró asistencia el ".self::resumenDeFechas($yaTrabajo).': no se la puede reemplazar ese día. Elija «Desde» a partir del día siguiente.'],
                    'sugerencias' => [], 'avisos' => [], 'total' => $filas->count(), 'filas' => $filas];
            }
        }

        // Una solicitud por cada combinación distinta de turno/zona/vehículo/personas: sus fechas viajan juntas.
        $grupos = [];

        foreach ($filas as $p) {
            $ayudantes = $p->ayudantes->pluck('id')->map(fn ($i) => (int) $i)->all();
            $vehiculo = $p->vehicle_id;
            $conductor = $p->driver_id;

            if ($d['rol'] === 'conductor') {
                $conductor = (int) $d['entrante_id'];
            } elseif ($d['rol'] === 'vehiculo') {
                $vehiculo = (int) $d['entrante_id'];
            } else {
                $ayudantes = array_map(fn (int $i) => $i === (int) $d['saliente_id'] ? (int) $d['entrante_id'] : $i, $ayudantes);
            }

            $clave = implode('|', [$p->shift_id, $p->zone_id, $vehiculo, $conductor, implode(',', $ayudantes)]);
            $grupos[$clave]['solicitud'] ??= ['turno_id' => $p->shift_id, 'zona_id' => $p->zone_id, 'vehiculo_id' => $vehiculo, 'conductor_id' => $conductor, 'ayudantes' => $ayudantes, 'fechas' => [], 'ignorar_ids' => [],
                'excluir' => $d['rol'] === 'vehiculo' ? [] : [(int) $d['saliente_id']]];
            $grupos[$clave]['solicitud']['fechas'][] = $p->date->toDateString();
            $grupos[$clave]['solicitud']['ignorar_ids'][] = $p->id;
        }

        $revision = $this->revisar(array_map(fn (array $g) => $g['solicitud'], array_values($grupos)));

        $avisos = $revision['avisos'];
        if ($d['desde'] < today()->toDateString()) {
            $avisos[] = 'Solo se reemplazan las programaciones desde hoy en adelante.';
        }

        return ['ok' => $revision['ok'], 'errores' => $revision['errores'], 'sugerencias' => $revision['sugerencias'], 'avisos' => $avisos, 'total' => $filas->count(), 'filas' => $filas];
    }

    /**
     * Aplica el reemplazo a todas las programaciones pendientes del rango (todo o nada). Cada cambio queda en el historial.
     *
     * @return int cantidad de programaciones modificadas
     *
     * @throws ValidationException
     */
    public function reemplazar(array $d): int
    {
        return $this->sinChoques(fn () => DB::transaction(function () use ($d) {
            $filas = $this->afectadasPorReemplazo($d);

            $personas = $filas->flatMap(fn (Programacion $p) => [$p->driver_id, ...$p->ayudantes->pluck('id')->all()]);
            $vehiculos = $filas->pluck('vehicle_id');

            if ($d['rol'] === 'vehiculo') {
                $vehiculos = $vehiculos->push((int) $d['entrante_id']);
            } else {
                $personas = $personas->push((int) $d['entrante_id']);
            }

            $this->bloquear($personas->filter()->unique()->sort()->values()->all(), $vehiculos->filter()->unique()->all());

            $revision = $this->revisarReemplazo($d);

            if (! $revision['ok']) {
                throw ValidationException::withMessages(['programacion' => $revision['errores']]);
            }

            foreach ($revision['filas'] as $p) {
                if ($d['rol'] === 'conductor') {
                    $p->update(['driver_id' => (int) $d['entrante_id']]);
                } elseif ($d['rol'] === 'vehiculo') {
                    $p->update(['vehicle_id' => (int) $d['entrante_id']]);
                } else {
                    $antes = $p->ayudantes->map->nombre_completo->implode(', ');
                    DB::table('programming_helpers')->where('programming_id', $p->id)->where('user_id', (int) $d['saliente_id'])->update(['user_id' => (int) $d['entrante_id']]);
                    $despues = $p->ayudantes()->get()->map->nombre_completo->implode(', ');
                    Bitacora::registrar('modificado', $p, ['ayudantes' => $antes], ['ayudantes' => $despues]);
                }
            }

            return $revision['total'];
        }, 3));
    }

    private function fecha(mixed $valor): ?Carbon
    {
        try {
            return filled($valor) ? Carbon::parse($valor)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
