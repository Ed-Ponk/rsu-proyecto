<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Bitacora;
use App\Models\Cambio;
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

    /** "el día 02/12/2026" o "los días 02/12/2026, 04/12/2026 y 07/12/2026": para completar frases. */
    public static function diasTexto(array $fechas): string
    {
        return (count($fechas) === 1 ? 'el día ' : 'los días ').self::resumenDeFechas($fechas);
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

    /*
     * Cada problema se guarda también de forma estructurada (quién, qué pasa, qué días, solución y, si aplica, una acción que
     * la pantalla puede ejecutar con un botón). Los textos largos de `errores` se mantienen para quien los necesite; la pantalla
     * pinta tarjetas cortas con esto.
     */

    /** @var list<array<string, mixed>> */
    private array $problemas = [];

    /** @var list<array{tipo: string, grupo: ?string, texto: string}> */
    private array $notas = [];

    private ?string $grupoActual = null;

    /** @var array<int, string> persona => 'conductor'|'ayudante' en la solicitud que se está revisando */
    private array $rolesActuales = [];

    private function problema(string $tipo, ?string $quien, string $que, array $fechas = [], ?string $solucion = null, ?int $personaId = null): void
    {
        $this->problemas[] = [
            'tipo' => $tipo, 'grupo' => $this->grupoActual, 'quien' => $quien, 'que' => $que, 'fechas' => array_values($fechas),
            'solucion' => $solucion, 'persona_id' => $personaId, 'rol' => $personaId ? ($this->rolesActuales[$personaId] ?? null) : null, 'accion' => null,
        ];
    }

    private function nota(string $tipo, string $texto): void
    {
        $this->notas[] = ['tipo' => $tipo, 'grupo' => $this->grupoActual, 'texto' => $texto];
    }

    /** "02/12, 04/12 y 07/12": cortas, para las notas. */
    private static function fechasCortas(array $fechas, int $mostrar = 3): string
    {
        $c = array_map(fn (string $f) => Carbon::parse($f)->format('d/m'), array_values($fechas));
        $resto = count($c) - $mostrar;

        return $resto > 0 ? implode(', ', array_slice($c, 0, $mostrar)).' y '.$resto.' más' : (count($c) > 1 ? implode(', ', array_slice($c, 0, -1)).' y '.end($c) : ($c[0] ?? ''));
    }

    /** Forma que espera el buscador de la pantalla (para poder poner a la persona sugerida con un clic). */
    private function itemDePersona(Personal $p): array
    {
        return [
            'id' => $p->id, 'titulo' => $p->nombre_completo, 'detalle' => "DNI {$p->dni} · ".($p->tipo?->name ?? 'Sin función'), 'foto' => $p->url_foto,
            'iniciales' => mb_strtoupper(mb_substr($p->name, 0, 1).mb_substr((string) $p->last_name, 0, 1)), 'insignia' => null,
        ];
    }

    /**
     * @param  array<int, array>  $solicitudes
     * @return array{ok: bool, errores: list<string>, sugerencias: list<string>, avisos: list<string>, plan: list<array>, problemas: list<array>, notas: list<array>}
     */
    public function revisar(array $solicitudes, bool $reiniciar = true): array
    {
        $this->horasDeTurnos = null;

        if ($reiniciar) {
            $this->problemas = [];
            $this->notas = [];
        }

        $errores = $sugerencias = $avisos = $plan = [];
        $ocupado = [];   // lo que ya tomaron los grupos anteriores del mismo lote
        $varios = count($solicitudes) > 1;

        foreach ($solicitudes as $solicitud) {
            $prefijo = $varios && filled($solicitud['etiqueta'] ?? null) ? '['.$solicitud['etiqueta'].'] ' : '';
            $this->grupoActual = $varios ? ($solicitud['etiqueta'] ?? null) : null;
            $resultado = $this->revisarUna($solicitud, $ocupado);

            $yaDiceElGrupo = fn (string $t) => filled($solicitud['etiqueta'] ?? null) && str_starts_with($t, $solicitud['etiqueta'].' ');

            foreach ($resultado['errores'] as $texto) {
                // Los errores de fechas valen para todos los grupos: se dicen una sola vez y sin prefijo.
                if (str_starts_with($texto, 'La fecha de') || str_starts_with($texto, 'El rango no puede') || str_starts_with($texto, 'Indique la fecha')) {
                    $errores[] = $texto;

                    continue;
                }

                $errores[] = ($yaDiceElGrupo($texto) ? '' : $prefijo).$texto;
            }
            foreach ($resultado['sugerencias'] as $texto) {
                $sugerencias[] = $prefijo.$texto;
            }
            foreach ($resultado['avisos'] as $texto) {
                $avisos[] = $prefijo.$texto;
            }

            $plan[] = ['etiqueta' => $solicitud['etiqueta'] ?? null, 'fechas' => $resultado['fechas']] + $solicitud;
        }

        $this->grupoActual = null;
        $errores = array_values(array_unique($errores));
        $avisos = array_values(array_unique($avisos));
        $total = array_sum(array_map(fn (array $p) => count($p['fechas']), $plan));

        if ($errores === [] && $total === 0) {
            $errores[] = 'No hay nada que generar: ningún día del rango coincide con los días de trabajo elegidos.';
            $this->problema('fechas', 'Sin días de trabajo', 'ningún día del rango coincide con los días elegidos', [], 'Amplíe el rango o marque más días.');
        }

        if ($total > self::MAX_PROGRAMACIONES) {
            $errores[] = "Se generarían {$total} programaciones y el máximo por vez es ".self::MAX_PROGRAMACIONES.'. Reduzca el rango de fechas o la cantidad de grupos.';
            $this->problema('limite', 'Demasiadas programaciones', "se generarían {$total} (el máximo por vez es ".self::MAX_PROGRAMACIONES.')', [], 'Reduzca el rango o la cantidad de grupos.');
        }

        // Los problemas de fechas valen para todos los grupos: se muestran una sola vez y sin grupo.
        $vistos = [];
        $problemas = [];
        foreach ($this->problemas as $p) {
            if (in_array($p['tipo'], ['fechas', 'dias', 'limite'], true)) {
                $p['grupo'] = null;
                $clave = $p['tipo'].'|'.$p['quien'].'|'.$p['que'];
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;
            }
            $problemas[] = $p;
        }

        return ['ok' => $errores === [], 'errores' => $errores, 'sugerencias' => $sugerencias, 'avisos' => $avisos, 'plan' => $plan, 'problemas' => $problemas, 'notas' => $this->notas];
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
    public function bloquear(array $personas, array $vehiculos): void
    {
        Personal::whereIn('users.id', $personas)->orderBy('users.id')->lockForUpdate()->get(['users.id']);
        Vehiculo::whereIn('id', $vehiculos)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    /**
     * Si a pesar de todo la base de datos detecta un duplicado (otro usuario guardó lo mismo en el mismo instante),
     * el usuario recibe un mensaje claro y no un error 500.
     */
    public function sinChoques(callable $operacion): mixed
    {
        try {
            return $operacion();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['programacion' => ['Otro usuario acaba de programar a esa persona o ese vehículo en la misma fecha y turno. Vuelva a validar la disponibilidad.']]);
        }
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
            $this->problema('fechas', 'Fechas', 'falta la fecha de inicio o la de fin', [], 'Complete las dos fechas.');

            return $sinFechas(['Indique la fecha de inicio y la fecha de fin.'], $avisos);
        }

        if ($fin->lt($inicio)) {
            $this->problema('fechas', 'Fecha de fin', 'es anterior a la de inicio', [], 'Elija una fecha de fin igual o posterior.');

            return $sinFechas(['La fecha de fin no puede ser anterior a la fecha de inicio.'], $avisos);
        }

        if (! $ignorar && $inicio->lt(today())) {
            $errores[] = 'La fecha de inicio no puede ser anterior a hoy.';
            $this->problema('fechas', 'Fecha de inicio', 'ya pasó', [], 'Elija hoy o una fecha futura.');
        }

        if ($inicio->diffInDays($fin) + 1 > self::MAX_DIAS_RANGO) {
            $this->problema('fechas', 'Rango de fechas', 'supera los '.self::MAX_DIAS_RANGO.' días', [], 'Use un rango más corto.');

            return $sinFechas([...$errores, 'El rango no puede superar '.self::MAX_DIAS_RANGO.' días.'], $avisos);
        }

        if ($dias === []) {
            $this->problema('dias', 'Días de trabajo', 'no hay ninguno marcado', [], 'Marque al menos un día.');

            return $sinFechas([...$errores, 'Seleccione al menos un día de trabajo.'], $avisos);
        }

        // Los feriados activos no se trabajan: se omiten y se avisa. Los inactivos no cuentan.
        $fechas = $this->fechas($inicio, $fin, $dias);
        $feriados = $ignorar
            ? collect()
            : Feriado::activo()->entre($inicio->toDateString(), $fin->toDateString())->get()->keyBy(fn (Feriado $f) => $f->date->toDateString());
        $enFeriado = array_values(array_filter($fechas, fn (string $f) => $feriados->has($f)));
        // El usuario puede decidir trabajar algunos feriados: esos se programan; el resto se omite.
        $usados = array_values(array_intersect($enFeriado, array_map('strval', (array) ($s['usar_feriados'] ?? []))));
        $omitidas = array_values(array_diff($enFeriado, $usados));
        $fechas = array_values(array_diff($fechas, $omitidas));

        if ($usados) {
            $this->nota('feriado', (count($usados) === 1 ? 'Se programa el feriado ' : 'Se programan los feriados ').implode(', ', array_map(
                fn (string $f) => Carbon::parse($f)->format('d/m').' ('.$feriados[$f]->description.')',
                array_slice($usados, 0, 2),
            )).(count($usados) > 2 ? ' y '.(count($usados) - 2).' más' : ''));
        }

        if ($omitidas) {
            $avisos[] = 'Se omitirán los feriados: '.implode(', ', array_map(
                fn (string $f) => Carbon::parse($f)->format('d/m/Y').' ('.$feriados[$f]->description.')',
                array_slice($omitidas, 0, 3),
            )).(count($omitidas) > 3 ? ' y '.(count($omitidas) - 3).' más' : '').'.';
            $this->nota('feriado', (count($omitidas) === 1 ? 'Se omite el feriado ' : 'Se omiten los feriados ').implode(', ', array_map(
                fn (string $f) => Carbon::parse($f)->format('d/m').' ('.$feriados[$f]->description.')',
                array_slice($omitidas, 0, 2),
            )).(count($omitidas) > 2 ? ' y '.(count($omitidas) - 2).' más' : ''));
        }

        if ($fechas === []) {
            $this->problema('fechas', 'Sin días de trabajo', 'ningún día del rango coincide con los días elegidos (o todos son feriados)', [], 'Amplíe el rango o cambie los días.');

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
            $this->problema('recurso', 'Turno', 'no es válido', [], 'Elija un grupo que tenga turno.');
        }

        if (! $zona) {
            $errores[] = 'Seleccione una zona válida.';
            $this->problema('recurso', 'Zona', 'no es válida', [], 'Elija un grupo que tenga zona.');
        } elseif (! $zona->status) {
            $errores[] = "La zona «{$zona->name}» está inactiva.";
            $this->problema('recurso', "La zona «{$zona->name}»", 'está inactiva', [], 'Active la zona o cambie la del grupo.');
        }

        if (! $vehiculo) {
            $errores[] = 'Seleccione un vehículo válido.';
            $this->problema('recurso', 'Vehículo', 'no es válido', [], 'Elija un grupo que tenga vehículo.');
        } elseif (! $vehiculo->status) {
            $errores[] = "El vehículo {$vehiculo->name} ({$vehiculo->plate}) está inactivo.";
            $this->problema('vehiculo_estado', "Vehículo {$vehiculo->plate}", 'está inactivo', [], 'Active el vehículo o cambie el del grupo.');
        }

        // Una programación nueva no puede ser de un turno que ya terminó (por ejemplo, la mañana de hoy a las 15:00).
        if ($turno && $ignorarIds === []) {
            $terminadas = array_values(array_filter($fechas, fn (string $f) => now()->gte($turno->finDe($f))));

            if ($terminadas) {
                $errores[] = "El turno {$turno->name} ({$turno->rango}) ya terminó ".self::diasTexto($terminadas).': no se puede programar un turno que ya pasó. Programe desde mañana o elija otro turno.';
                $this->problema('fechas', "Turno {$turno->name}", 'ya terminó '.self::diasTexto($terminadas), $terminadas, 'Programe desde mañana o elija otro turno.');
            }
        }

        // Una programación nueva no puede ser de un turno que ya terminó (por ejemplo, la mañana de hoy a las 15:00).
        if ($turno && $ignorarIds === []) {
            $terminadas = array_values(array_filter($fechas, fn (string $f) => now()->gte($turno->finDe($f))));

            if ($terminadas) {
                $errores[] = "El turno {$turno->name} ({$turno->rango}) ya terminó ".self::diasTexto($terminadas).': no se puede programar un turno que ya pasó. Programe desde mañana o elija otro turno.';
                $this->problema('fechas', "Turno {$turno->name}", 'ya terminó '.self::diasTexto($terminadas), $terminadas, 'Programe desde mañana o elija otro turno.');
            }
        }

        $idConductor = (int) ($s['conductor_id'] ?? 0);
        $idsAyudantes = array_values(array_filter(array_map('intval', (array) ($s['ayudantes'] ?? []))));
        $todos = array_values(array_filter([$idConductor, ...$idsAyudantes]));
        $this->rolesActuales = array_fill_keys($idsAyudantes, 'ayudante') + ($idConductor ? [$idConductor => 'conductor'] : []);

        if (count($todos) !== count(array_unique($todos))) {
            $repetidos = Personal::whereIn('users.id', array_keys(array_filter(array_count_values($todos), fn ($n) => $n > 1)))->get()->map->nombre_completo->implode(', ');
            $errores[] = ($repetidos !== '' ? "{$repetidos} aparece más de una vez" : 'Hay una persona repetida').' en la programación: una persona no puede ocupar dos puestos (conductor y ayudante, o dos veces de ayudante).';
            $this->problema('duplicado', $repetidos !== '' ? $repetidos : 'Persona repetida', 'aparece más de una vez', [], 'Cada persona ocupa un solo puesto: quite la repetida.');
        }

        if (! $idConductor) {
            $errores[] = 'Seleccione al conductor.';
            $this->problema('rol', 'Conductor', 'falta elegirlo', [], 'Elija un conductor.');
        }

        if (! $idsAyudantes) {
            $errores[] = 'Seleccione al menos un ayudante.';
            $this->problema('rol', 'Ayudantes', 'falta elegir al menos uno', [], 'Elija un ayudante.');
        }

        if ($vehiculo && 1 + count($idsAyudantes) > $vehiculo->occupant_capacity) {
            $cabe = (int) $vehiculo->occupant_capacity;
            $ayudantesQueCaben = max(0, $cabe - 1);
            $llevan = count($idsAyudantes);
            $errores[] = "El vehículo {$vehiculo->name} ({$vehiculo->plate}) solo tiene lugar para {$cabe} ".($cabe === 1 ? 'persona' : 'personas')
                .": 1 conductor y {$ayudantesQueCaben} ".($ayudantesQueCaben === 1 ? 'ayudante' : 'ayudantes').". Aquí van 1 conductor y {$llevan} "
                .($llevan === 1 ? 'ayudante' : 'ayudantes').': quite ayudantes o cambie el vehículo del grupo.';
            $this->problema('capacidad', "Vehículo {$vehiculo->plate}", "solo lleva {$cabe} ".($cabe === 1 ? 'persona' : 'personas')." (1 conductor y {$ayudantesQueCaben} ".($ayudantesQueCaben === 1 ? 'ayudante' : 'ayudantes').") y aquí van {$llevan}", [],
                'Quite ayudantes o cambie el vehículo del grupo.');
        }

        $personas = Personal::with('tipo')->whereIn('users.id', $todos)->get()->keyBy('id');

        foreach ($todos as $id) {
            if (! $personas->has($id)) {
                $errores[] = 'Una de las personas elegidas ya no existe.';
                $this->problema('rol', 'Persona', 'ya no existe', [], 'Elíjala de nuevo.');
            }
        }

        $conductor = $personas->get($idConductor);
        if ($conductor && $conductor->usertype_id !== TipoPersonal::conductor()?->id) {
            $errores[] = "{$conductor->nombre_completo} no es conductor: en ese puesto elija a un conductor.";
            $this->problema('rol', $conductor->nombre_completo, 'no es conductor', [], 'En ese puesto elija a un conductor.', $conductor->id);
        }

        foreach ($idsAyudantes as $id) {
            $ayudante = $personas->get($id);
            if ($ayudante && $ayudante->usertype_id !== TipoPersonal::ayudante()?->id) {
                $errores[] = "{$ayudante->nombre_completo} no es ayudante: en ese puesto elija a un ayudante.";
                $this->problema('rol', $ayudante->nombre_completo, 'no es ayudante', [], 'En ese puesto elija a un ayudante.', $ayudante->id);
            }
        }

        $fechasAComprobar = $fechas;

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

                $todas = count($repetidas) === count($fechas);
                $this->problema('grupo', $nombre, $todas ? 'ya está programado en todos esos días' : 'ya está programado en '.count($repetidas).' de esos días', $repetidas,
                    $todas ? 'Ya aparecen en la lista. Para cambiar personas use «Reemplazar».' : 'Empiece el rango el '.Carbon::parse(end($repetidas))->addDay()->format('d/m').' o use «Reemplazar» para cambiar personas.');

                if (count($repetidas) === count($fechas)) {
                    return ['errores' => [...$errores, $mensaje], 'avisos' => $avisos, 'fechas' => $fechas, 'sugerencias' => [
                        'Para ver esos días, filtre la lista de programaciones por esas fechas.',
                        'Para cambiar al conductor, a un ayudante o el vehículo en esos días, use «Reemplazar».',
                    ]];
                }

                $errores[] = $mensaje;
                // Esos días ya quedaron explicados: no se repiten como "persona ocupada" ni "vehículo en uso".
                $fechasAComprobar = array_values(array_diff($fechas, $repetidas));
                $sugerencias[] = 'Empiece el rango después del '.Carbon::parse(end($repetidas))->format('d/m/Y').' para programar solo los días que faltan, o use «Reemplazar» para cambiar personas en los días ya programados.';
            }
        }

        if ($turno && $personas->isNotEmpty()) {
            [$mensajes, $malas, $avisosPersonas] = $this->disponibilidad($personas, $fechasAComprobar, $turno->id, $vehiculo, $ignorarIds, $ocupado);
            $errores = [...$errores, ...$mensajes];
            $avisos = [...$avisos, ...$avisosPersonas];
            // Un reemplazo sugerido se reserva: no se ofrece a la misma persona para dos ausentes. Tampoco se sugiere a
            // quien se está sacando de la programación (reemplazos).
            $reservados = [...$todos, ...array_map('intval', (array) ($s['excluir'] ?? []))];

            foreach ($malas as $id => $fechasMalas) {
                $persona = $personas[$id];
                // El reemplazo se pone en TODOS los días de la programación, no solo en los que fallan: tiene que poder cubrirlos todos.
                // Tampoco se descarta a la persona inactiva: también necesita que le ofrezcan reemplazo.
                $reemplazo = $this->buscarReemplazo($persona, $fechasAComprobar, $turno->id, $reservados, $ignorarIds, $ocupado);
                // Si nadie cubre todo el rango, se busca quien cubra al menos los días con problema (para usar «Reemplazar» solo en esos días).
                $soloEsosDias = $reemplazo ? null : $this->buscarReemplazo($persona, $fechasMalas, $turno->id, $reservados, $ignorarIds, $ocupado);

                if ($reemplazo) {
                    $reservados[] = $reemplazo->id;
                }

                $funcion = Str::lower($persona->tipo?->name ?? 'persona');

                $sugerencias[] = $reemplazo
                    ? "Cambie a {$persona->nombre_completo} por {$reemplazo->nombre_completo}: es {$funcion} y está libre en esos días."
                    : ($soloEsosDias
                        ? "Ningún {$funcion} libre puede cubrir todo el rango. {$soloEsosDias->nombre_completo} sí puede cubrir a {$persona->nombre_completo} ".self::diasTexto($fechasMalas).': programe el grupo y luego use «Reemplazar» solo para esos días.'
                        : "No hay otro {$funcion} libre para cubrir a {$persona->nombre_completo} en esos días: los demás ya están programados, de vacaciones o sin contrato vigente.");

                // La misma sugerencia, pero colgada de los problemas de esa persona (con botón si hay reemplazo).
                foreach ($this->problemas as &$problema) {
                    if (($problema['persona_id'] ?? null) === $id && $problema['grupo'] === $this->grupoActual && $problema['solucion'] === null) {
                        $problema['solucion'] = $reemplazo ? "Cambiar por {$reemplazo->nombre_completo}" : ($soloEsosDias ? "Nadie cubre todo el rango: use «Reemplazar» solo para esos días ({$soloEsosDias->nombre_completo})" : "No hay otro {$funcion} libre: revise contratos y vacaciones");
                        $problema['accion'] = $reemplazo ? ['tipo' => 'cambiar', 'persona_id' => $id, 'rol' => $problema['rol'], 'por' => $this->itemDePersona($reemplazo)] : null;
                    }
                }
                unset($problema);
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
        $clave = $fecha.'|'.$turnoId;

        if (isset($this->ventanas[$clave])) {
            return $this->ventanas[$clave];
        }

        [$inicio, $fin] = $this->horasDelTurno($turnoId);
        $dia = Carbon::parse($fecha)->startOfDay();

        return $this->ventanas[$clave] = [$dia->copy()->addMinutes($inicio), $dia->copy()->addMinutes($fin <= $inicio ? $fin + 1440 : $fin)];
    }

    /** @var array<string, array{0: Carbon, 1: Carbon}> */
    private array $ventanas = [];

    /**
     * Turnos de una persona como mapas «inicio → fines» y «fin → inicios» (en segundos), para encontrar en O(1) los turnos pegados.
     *
     * @param  list<array{0: string, 1: int}>  $turnos  [fecha, turno]
     * @return array{0: array<int, list<int>>, 1: array<int, list<int>>}
     */
    private function mapasDeTurnos(array $turnos): array
    {
        $porInicio = $porFin = [];

        foreach ($turnos as [$fecha, $turnoId]) {
            [$a, $b] = $this->ventana($fecha, $turnoId);
            $porInicio[$a->timestamp][] = $b->timestamp;
            $porFin[$b->timestamp][] = $a->timestamp;
        }

        return [$porInicio, $porFin];
    }

    /** Horas de trabajo seguidas que sumaría un turno nuevo con los turnos pegados a él (uno termina justo cuando empieza el otro). */
    private function horasSeguidas(array $mapas, string $fecha, int $turnoId): float
    {
        [$porInicio, $porFin] = $mapas;
        [$a, $b] = $this->ventana($fecha, $turnoId);
        $inicio = $a->timestamp;
        $fin = $b->timestamp;

        while (isset($porFin[$inicio])) {
            $inicio = min($porFin[$inicio]);
        }

        while (isset($porInicio[$fin])) {
            $fin = max($porInicio[$fin]);
        }

        return ($fin - $inicio) / 3600;
    }

    /**
     * Cómo choca un turno con otro de la misma persona. Trabajar dos turnos el mismo día (incluso seguidos) está permitido;
     * solo se rechaza:
     *   'igual' = el mismo turno el mismo día,
     *   'cruce' = turnos cuyos horarios se pisan (un nocturno que termina a las 06:00 no pisa a uno que empieza a las 06:00),
     *   null    = no chocan.
     */
    private function choqueDeHorario(string $fecha, int $turnoId, string $otraFecha, int $otroTurno): ?string
    {
        if ($fecha === $otraFecha && $turnoId === $otroTurno) {
            return 'igual';
        }

        [$aInicio, $aFin] = $this->ventana($fecha, $turnoId);
        [$bInicio, $bFin] = $this->ventana($otraFecha, $otroTurno);

        return $aInicio->lt($bFin) && $bInicio->lt($aFin) ? 'cruce' : null;
    }

    /**
     * Turnos ya programados de cada persona (cualquier turno, con un día de margen a cada lado: un turno de noche termina al día siguiente).
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
        $nombresDeTurnos = Turno::pluck('name', 'id')->all();
        $turnoActual = $nombresDeTurnos[$turnoId] ?? 'de este grupo';

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
            $porContrato = $porVacaciones = $porOcupacion = $porLote = $porCruce = $porCadena = $propias = [];
            $choque = null;   // primer turno con el que se cruza, para explicar el porqué: [fecha, turno]
            $maxHoras = (float) config('proyecto.programacion.max_horas_seguidas', 16);
            $mapas = $this->mapasDeTurnos([...($existentes[$id] ?? []), ...($ocupado['int'][$id] ?? [])]);
            $mayorCadena = 0.0;

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
                        'cruce' => $porCruce[] = $fecha,
                        default => null,
                    };
                    if ($tipo === 'cruce') {
                        $choque ??= [$otraFecha, $otroTurno];
                    }
                }

                foreach ($ocupado['int'][$id] ?? [] as [$otraFecha, $otroTurno]) {
                    $tipo = $this->choqueDeHorario($fecha, $turnoId, $otraFecha, $otroTurno);
                    match ($tipo) {
                        'igual' => $porLote[] = $fecha,
                        'cruce' => $porCruce[] = $fecha,
                        default => null,
                    };
                    if ($tipo === 'cruce') {
                        $choque ??= [$otraFecha, $otroTurno];
                    }
                }

                // Turnos pegados: dos seguidos (16 h) sí; tres (24 h sin parar) no.
                if (($horas = $this->horasSeguidas($mapas, $fecha, $turnoId)) > $maxHoras) {
                    $porCadena[] = $fecha;
                    $mayorCadena = max($mayorCadena, $horas);
                }

                // Turnos pegados: dos seguidos (16 h) sí; tres (24 h sin parar) no.
                if (($horas = $this->horasSeguidas($mapas, $fecha, $turnoId)) > $maxHoras) {
                    $porCadena[] = $fecha;
                    $mayorCadena = max($mayorCadena, $horas);
                }

                // Las fechas de esta misma solicitud no chocan entre sí: se guardan aparte y se suman al terminar (evita revisar cada fecha contra todas las anteriores).
                $propias[] = [$fecha, $turnoId];
            }
            $ocupado['int'][$id] = [...($ocupado['int'][$id] ?? []), ...$propias];

            if (! $persona->status) {
                $mensajes[] = "{$persona->nombre_completo} está inactivo y no se puede programar.";
                $this->problema('inactivo', $persona->nombre_completo, 'está inactivo', [], null, $id);
                $malas[$id] = $fechas;
            }

            // Si el contrato existe pero termina dentro del rango, se dice cuándo vence en vez de un mensaje genérico.
            $vence = ($contratos[$id] ?? collect())->filter(fn (Contrato $c) => $c->end_date)->map(fn (Contrato $c) => $c->end_date->toDateString())->max();
            $textoContrato = $porContrato && $vence && $vence >= $desde && $vence < end($porContrato)
                ? 'tiene el contrato vigente hasta el '.Carbon::parse($vence)->format('d/m/Y').': no cubre {fechas}'
                : 'no tiene contrato vigente {fechas}';

            $textoCruce = $choque
                ? 'ya tiene el turno '.($nombresDeTurnos[$choque[1]] ?? '').' el '.Carbon::parse($choque[0])->format('d/m/Y').', que se cruza con el turno '.$turnoActual.' {fechas}'
                : 'tiene otro turno que se cruza con el turno '.$turnoActual.' {fechas}';

            $queCorto = [
                'vacaciones' => 'está de vacaciones',
                'contrato' => $porContrato && $vence && $vence >= $desde && $vence < end($porContrato) ? 'su contrato vence el '.Carbon::parse($vence)->format('d/m') : 'no tiene contrato vigente',
                'ocupado' => "ya está programado en el turno {$turnoActual}",
                'lote' => 'está en dos grupos a la vez (mismo turno)',
                'cadena' => 'pasaría de '.rtrim(rtrim(number_format($maxHoras, 1), '0'), '.').' h de trabajo seguidas',
                'cruce' => $choque ? 'ya tiene el turno '.($nombresDeTurnos[$choque[1]] ?? '').' el '.Carbon::parse($choque[0])->format('d/m').', que se cruza' : 'tiene otro turno que se cruza',
            ];

            $detalles = [
                ['vacaciones', $porVacaciones, 'tiene vacaciones aprobadas {fechas}'],
                ['contrato', $porContrato, $textoContrato],
                ['ocupado', array_values(array_unique($porOcupacion)), "ya está programado en el turno {$turnoActual} {fechas}"],
                ['lote', array_values(array_unique($porLote)), "está en dos de los grupos elegidos, en el turno {$turnoActual}, {fechas}"],
                ['cruce', array_values(array_unique($porCruce)), $textoCruce],
            ];

            foreach ($detalles as [$tipoProblema, $afectadas, $texto]) {
                if ($afectadas) {
                    sort($afectadas);
                    $mensajes[] = "{$persona->nombre_completo} ".str_replace('{fechas}', self::diasTexto($afectadas), $texto).'.';
                    $this->problema($tipoProblema, $persona->nombre_completo, $queCorto[$tipoProblema], $afectadas, null, $id);
                    $malas[$id] = array_values(array_unique([...($malas[$id] ?? []), ...$afectadas]));
                }
            }

            // Doble y triple turno están permitidos, pero más de N horas sin parar se avisa (no bloquea).
            if ($porCadena) {
                $porCadena = array_values(array_unique($porCadena));
                sort($porCadena);
                $horas = rtrim(rtrim(number_format($mayorCadena, 1), '0'), '.');
                $avisos[] = "{$persona->nombre_completo} trabajaría {$horas} h seguidas con el turno {$turnoActual} ".self::diasTexto($porCadena).': tiene turnos pegados antes o después (el aviso salta desde las '.rtrim(rtrim(number_format($maxHoras, 1), '0'), '.').' h).';
                $this->nota('cadena', "{$persona->nombre_completo} trabajaría {$horas} h seguidas");
            }

            if (($faltas[$id] ?? 0) >= (int) config('proyecto.programacion.faltas_alerta', 3)) {
                $avisos[] = "{$persona->nombre_completo} tiene {$faltas[$id]} falta(s) en los últimos {$diasFaltas} días.";
                $this->nota('faltas', "{$persona->nombre_completo} tiene {$faltas[$id]} faltas en los últimos {$diasFaltas} días");
            }

            $limite = Carbon::parse($hasta)->addDays((int) config('proyecto.programacion.contrato_por_vencer_dias', 15))->toDateString();
            if ($vence && $vence >= $hasta && $vence <= $limite && ! $porContrato) {
                $avisos[] = "El contrato de {$persona->nombre_completo} vence el ".Carbon::parse($vence)->format('d/m/Y').', pocos días después del último día programado.';
                $this->nota('contrato', "El contrato de {$persona->nombre_completo} vence el ".Carbon::parse($vence)->format('d/m').', poco después del último día');
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
                $mensajes[] = "El vehículo {$vehiculo->name} ({$vehiculo->plate}) ya está en otra programación del turno {$turnoActual} ".self::diasTexto($afectadas).'.';
                $this->problema('vehiculo', "Vehículo {$vehiculo->plate}", 'ya está en otra programación de este turno', $afectadas, 'Cambie el vehículo o el turno del grupo.');
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

    /**
     * ¿Quien entra a cubrir tiene que haber marcado asistencia? Sí, si es hoy y el turno ya empezó.
     * Con `$turnoId` se mide el turno al que se está cambiando la programación (el editor puede cambiarlo a la vez).
     */
    public function exigeAsistencia(Programacion $programacion, ?int $turnoId = null): bool
    {
        $turno = $turnoId && $turnoId !== (int) $programacion->shift_id ? Turno::find($turnoId) : null;

        // Se mide con la ventana real del turno (inicio y fin de ESA jornada): un nocturno de ayer sigue en curso hasta las 06:00.
        return $programacion->haEmpezado($turno) && ! $programacion->haTerminado($turno);
    }

    /**
     * Revisa lo que exige la asistencia cuando alguien entra a una programación o se la mueve de turno. Devuelve el mensaje de error o null.
     *  - Mover la programación a un turno que ya terminó no se puede.
     *  - Si el turno (el de destino, si se cambia) ya empezó, quien entra tiene que haber marcado EN ESE turno; si se cambia de turno,
     *    todo el personal tiene que haber marcado en el turno de destino.
     *
     * @param  list<int>  $entran  personas que se agregan a la programación
     * @param  list<int>  $todos  todo el personal que quedaría en la programación
     */
    public function problemaDeAsistenciaAlEntrar(Programacion $programacion, int $turnoId, array $entran, array $todos): ?string
    {
        $cambiaTurno = $turnoId !== (int) $programacion->shift_id;
        $turno = $cambiaTurno ? Turno::find($turnoId) : $programacion->turno;

        if (! $turno) {
            return null;
        }

        if ($cambiaTurno && $programacion->haTerminado($turno)) {
            return "El turno {$turno->name} del {$programacion->date->format('d/m/Y')} ya terminó: no se puede mover la programación a ese turno.";
        }

        if (! $programacion->haEmpezado($turno)) {
            return null;
        }

        $requeridos = $cambiaTurno ? $todos : $entran;

        if ($requeridos === []) {
            return null;
        }

        $presentes = $this->presentesEnTurno($requeridos, $programacion->date->toDateString(), $turno->id);
        $faltan = Personal::whereIn('users.id', array_diff($requeridos, $presentes))->get();

        return $faltan->isEmpty() ? null
            : $faltan->map->nombre_completo->implode(', ')." no registró asistencia en el turno {$turno->name}: el turno ya empezó y quien cubre tiene que estar presente en ese mismo turno.";
    }

    /**
     * Ids de las personas que marcaron asistencia en ese turno y jornada: quien cubre tiene que estar trabajando en
     * el MISMO turno de la programación; la asistencia de otro turno del día no sirve.
     *
     * @param  iterable<int>  $ids
     * @return list<int>
     */
    public function presentesEnTurno(iterable $ids, string $fecha, int $turnoId): array
    {
        return Asistencia::presentesEnTurno($fecha, $turnoId)->whereIn('user_id', collect($ids)->all())
            ->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Personas que podrían tomar el lugar de `$salienteId` en una programación, con el motivo si no están disponibles:
     * vacaciones, sin contrato, ocupadas en ese turno u otro que se cruza y, si el turno de hoy ya empezó, sin asistencia.
     * Con `$turnoId` se evalúa para ese turno (el editor puede estar cambiando el turno a la vez).
     *
     * @return list<array{persona: array, disponible: bool, motivo: ?string}>
     */
    public function candidatos(Programacion $programacion, string $rol, int $salienteId, ?int $turnoId = null): array
    {
        $this->horasDeTurnos = null;
        $this->problemas = [];
        $this->notas = [];
        $this->grupoActual = null;
        $this->rolesActuales = [];

        $funcion = $rol === 'conductor' ? TipoPersonal::conductor() : TipoPersonal::ayudante();
        $enLaProgramacion = [$programacion->driver_id, ...$programacion->ayudantes->pluck('id')->all()];

        $personas = Personal::activos()->with('tipo')->where('users.usertype_id', $funcion?->id)
            ->whereNotIn('users.id', [...$enLaProgramacion, $salienteId])
            ->orderBy('users.last_name')->orderBy('users.name')->limit(100)->get();

        if ($personas->isEmpty()) {
            return [];
        }

        $fecha = $programacion->date->toDateString();
        $turnoId ??= (int) $programacion->shift_id;
        $ocupado = [];
        // Mismo cargo (arriba), libre en ese turno: si ya está programado en otra zona a esa hora, no puede cubrir.
        $this->disponibilidad($personas->keyBy('id'), [$fecha], $turnoId, null, [$programacion->id], $ocupado);

        $motivos = collect($this->problemas)->groupBy('persona_id')->map(fn (Collection $l) => $l->pluck('que')->unique()->implode('; '));

        // Si el turno de hoy ya empezó, quien va a cubrir tiene que estar presente EN ESE TURNO.
        $exigeAsistencia = $this->exigeAsistencia($programacion, $turnoId);
        $presentes = $exigeAsistencia ? $this->presentesEnTurno($personas->pluck('id'), $fecha, $turnoId) : [];
        $turnoDestino = Turno::find($turnoId);
        $nombreTurno = $turnoDestino?->name;
        // Si el turno de la programación (o el de destino) ya terminó, la programación ya no se puede modificar: nadie puede entrar.
        $terminado = $programacion->haTerminado() || ($turnoDestino && $programacion->haTerminado($turnoDestino));

        $lista = $personas->map(function (Personal $p) use ($motivos, $exigeAsistencia, $presentes, $nombreTurno, $terminado) {
            $motivo = $terminado ? 'el turno ya terminó: la programación ya no se puede modificar' : ($motivos[$p->id] ?? null);

            if (! $motivo && $exigeAsistencia && ! in_array((int) $p->id, $presentes, true)) {
                $motivo = "no registró asistencia en el turno {$nombreTurno}";
            }

            return ['persona' => $this->itemDePersona($p), 'disponible' => $motivo === null, 'motivo' => $motivo];
        });

        return $lista->sortBy(fn (array $c) => $c['disponible'] ? 0 : 1)->values()->all();
    }

    /* ------------------------------------------------------------------ reemplazo masivo */

    /** Zonas a las que se limita un cambio masivo (vacío = todas). Acepta `zona_ids` (lista) y `zona_id` (una sola). @return list<int> */
    private function zonasDe(array $d): array
    {
        return array_values(array_unique(array_filter(array_map('intval', [...(array) ($d['zona_ids'] ?? []), ...(array) ($d['zona_id'] ?? [])]))));
    }

    /** Programaciones pendientes (desde hoy) afectadas por un reemplazo. */
    private function afectadasPorReemplazo(array $d, bool $bloquear = false)
    {
        // Se parte de ayer: el turno nocturno de ayer puede seguir en curso. Los turnos que ya terminaron se descartan abajo.
        $desde = max($d['desde'], today()->subDay()->toDateString());

        // Con $bloquear la lectura es con bloqueo (FOR UPDATE): ve lo último confirmado y frena a quien quiera cambiar las mismas
        // programaciones a la vez. Una lectura normal vería la «foto» anterior y dos cambios simultáneos se pisarían.
        return Programacion::query()->with('turno')->when(! $bloquear, fn ($q) => $q->with('ayudantes'))->pendientes()
            ->entre($desde, $d['hasta'])
            ->when($this->zonasDe($d) !== [], fn ($q) => $q->whereIn('zone_id', $this->zonasDe($d)))
            ->when($d['rol'] === 'turno', fn ($q) => $q->where('shift_id', $d['saliente_id']))
            ->when($d['rol'] === 'conductor', fn ($q) => $q->where('driver_id', $d['saliente_id']))
            ->when($d['rol'] === 'vehiculo', fn ($q) => $q->where('vehicle_id', $d['saliente_id']))
            ->when($d['rol'] === 'ayudante', fn ($q) => $q->whereHas('ayudantes', fn ($a) => $a->where('users.id', $d['saliente_id'])))
            ->when($bloquear, fn ($q) => $q->orderBy('id')->lockForUpdate(), fn ($q) => $q->orderBy('date'))->get()
            ->reject(fn (Programacion $p) => $p->haTerminado())->values();
    }

    /**
     * Revisa un reemplazo (conductor, ayudante o vehículo) en las programaciones pendientes de un rango, sin guardar nada.
     *
     * @param  array{rol: string, saliente_id: int, entrante_id: int, desde: string, hasta: string}  $d
     * @return array{ok: bool, errores: list<string>, sugerencias: list<string>, avisos: list<string>, total: int, filas: Collection}
     */
    /** Lote (batch_id) de los cambios que dejó el último `reemplazar`. */
    private ?string $ultimoLote = null;

    public function ultimoLote(): ?string
    {
        return $this->ultimoLote;
    }

    public function revisarReemplazo(array $d): array
    {
        $this->horasDeTurnos = null;
        $this->problemas = [];
        $this->notas = [];
        $this->grupoActual = null;
        $fin = fn (array $r) => $r + ['problemas' => $this->problemas, 'notas' => $this->notas];

        if ((int) $d['saliente_id'] === (int) $d['entrante_id']) {
            $this->problema('reemplazo', 'Reemplazo', 'quien sale y quien entra son lo mismo', [], 'Elija a otra persona (u otro vehículo) para entrar.');

            return $fin(['ok' => false, 'errores' => ['Quien sale y quien entra no pueden ser el mismo.'], 'sugerencias' => [], 'avisos' => [], 'total' => 0, 'filas' => collect()]);
        }

        $filas = $this->afectadasPorReemplazo($d);

        if ($filas->isEmpty()) {
            $this->problema('reemplazo', 'Sin programaciones', 'no hay programaciones pendientes con esa persona o vehículo en el rango', [], 'Amplíe el rango de fechas.');

            return $fin(['ok' => false, 'errores' => ['No hay programaciones pendientes con esa persona o vehículo en el rango elegido.'], 'sugerencias' => [], 'avisos' => [], 'total' => 0, 'filas' => $filas]);
        }

        // Marcaciones «presente» de la jornada de cada programación afectada, por persona y TURNO («usuario|turno|fecha»).
        $marcaron = Asistencia::where('status', Asistencia::PRESENTE)
            ->whereIn('shift_date', $filas->map(fn (Programacion $p) => $p->date->toDateString())->unique()->values()->all())
            ->get(['user_id', 'schedule_id', 'shift_date'])
            ->mapWithKeys(fn (Asistencia $a) => [$a->user_id.'|'.$a->schedule_id.'|'.$a->shift_date->toDateString() => true]);
        $marco = fn (int $persona, Programacion $p) => isset($marcaron[$persona.'|'.$p->shift_id.'|'.$p->date->toDateString()]);

        if (in_array($d['rol'], ['conductor', 'ayudante'], true)) {
            // Quien ya registró asistencia en el turno de la programación está trabajando: se reemplaza desde el día siguiente.
            $yaTrabajo = $filas->filter(fn (Programacion $p) => $marco((int) $d['saliente_id'], $p))
                ->map(fn (Programacion $p) => $p->date->toDateString())->unique()->sort()->values()->all();

            if ($yaTrabajo) {
                $nombre = Personal::find($d['saliente_id'])?->nombre_completo ?? 'La persona';

                $this->problema('asistencia', $nombre, 'ya registró asistencia en ese turno', $yaTrabajo, 'Elija «Desde» a partir del día siguiente.');

                return $fin(['ok' => false, 'errores' => ["{$nombre} ya registró asistencia en el turno de la programación el ".self::resumenDeFechas($yaTrabajo).': no se la puede reemplazar ese día. Elija «Desde» a partir del día siguiente.'],
                    'sugerencias' => [], 'avisos' => [], 'total' => $filas->count(), 'filas' => $filas]);
            }

            // Si el turno de hoy ya empezó, quien entra a cubrir tiene que estar presente en ESE turno.
            $sinMarcar = $filas->filter(fn (Programacion $p) => $this->exigeAsistencia($p) && ! $marco((int) $d['entrante_id'], $p));

            if ($sinMarcar->isNotEmpty()) {
                $nombre = Personal::find($d['entrante_id'])?->nombre_completo ?? 'La persona';
                $turnos = $sinMarcar->map(fn (Programacion $p) => $p->turno?->name)->filter()->unique()->implode(', ');

                $this->problema('asistencia', $nombre, "no registró asistencia en el turno {$turnos}", [], 'Elija a alguien que ya marcó asistencia en ese turno, o «Desde» a partir de mañana.');

                return $fin(['ok' => false, 'errores' => ["{$nombre} no registró asistencia hoy en el turno {$turnos}: el turno ya empezó y quien cubre tiene que estar presente en ese mismo turno."],
                    'sugerencias' => [], 'avisos' => [], 'total' => $filas->count(), 'filas' => $filas]);
            }
        }

        if ($d['rol'] === 'turno' && ($destino = Turno::find($d['entrante_id']))) {
            // No se mueve nada a un turno que ya terminó, y si el turno de destino ya empezó todo el personal tiene que haber marcado allí.
            $terminadas = $filas->filter(fn (Programacion $p) => $p->haTerminado($destino));

            if ($terminadas->isNotEmpty()) {
                $fechas = $terminadas->map(fn (Programacion $p) => $p->date->toDateString())->unique()->sort()->values()->all();
                $this->problema('asistencia', "Turno {$destino->name}", 'ya terminó', $fechas, 'Elija «Desde» a partir de mañana.');

                return $fin(['ok' => false, 'errores' => ["El turno {$destino->name} ya terminó el ".self::resumenDeFechas($fechas).': no se puede mover programaciones a un turno que ya terminó. Elija «Desde» a partir de mañana.'],
                    'sugerencias' => [], 'avisos' => [], 'total' => $filas->count(), 'filas' => $filas]);
            }

            $empezadas = $filas->filter(fn (Programacion $p) => $p->haEmpezado($destino));

            if ($empezadas->isNotEmpty()) {
                $enDestino = Asistencia::where('status', Asistencia::PRESENTE)->where('schedule_id', $destino->id)
                    ->whereIn('shift_date', $empezadas->map(fn (Programacion $p) => $p->date->toDateString())->unique()->values()->all())
                    ->get(['user_id', 'shift_date'])->mapWithKeys(fn (Asistencia $a) => [$a->user_id.'|'.$a->shift_date->toDateString() => true]);
                $sinMarcar = $empezadas->filter(fn (Programacion $p) => collect([$p->driver_id, ...$p->ayudantes->pluck('id')->all()])
                    ->contains(fn ($persona) => ! isset($enDestino[$persona.'|'.$p->date->toDateString()])));

                if ($sinMarcar->isNotEmpty()) {
                    $fechas = $sinMarcar->map(fn (Programacion $p) => $p->date->toDateString())->unique()->sort()->values()->all();
                    $this->problema('asistencia', "Turno {$destino->name}", 'ya empezó y el personal no marcó en él', $fechas, 'Elija «Desde» a partir de mañana.');

                    return $fin(['ok' => false, 'errores' => ["El turno {$destino->name} ya empezó el ".self::resumenDeFechas($fechas).' y el personal de esas programaciones no registró asistencia en él: no se puede mover ahí. Elija «Desde» a partir de mañana.'],
                        'sugerencias' => [], 'avisos' => [], 'total' => $filas->count(), 'filas' => $filas]);
                }
            }
        }

        if ($d['rol'] === 'turno' && ($destino = Turno::find($d['entrante_id']))) {
            // No se mueve nada a un turno que ya terminó, y si el turno de destino ya empezó todo el personal tiene que haber marcado allí.
            $terminadas = $filas->filter(fn (Programacion $p) => $p->haTerminado($destino));

            if ($terminadas->isNotEmpty()) {
                $fechas = $terminadas->map(fn (Programacion $p) => $p->date->toDateString())->unique()->sort()->values()->all();
                $this->problema('asistencia', "Turno {$destino->name}", 'ya terminó', $fechas, 'Elija «Desde» a partir de mañana.');

                return $fin(['ok' => false, 'errores' => ["El turno {$destino->name} ya terminó el ".self::resumenDeFechas($fechas).': no se puede mover programaciones a un turno que ya terminó. Elija «Desde» a partir de mañana.'],
                    'sugerencias' => [], 'avisos' => [], 'total' => $filas->count(), 'filas' => $filas]);
            }

            $empezadas = $filas->filter(fn (Programacion $p) => $p->haEmpezado($destino));

            if ($empezadas->isNotEmpty()) {
                $enDestino = Asistencia::where('status', Asistencia::PRESENTE)->where('schedule_id', $destino->id)
                    ->whereIn('shift_date', $empezadas->map(fn (Programacion $p) => $p->date->toDateString())->unique()->values()->all())
                    ->get(['user_id', 'shift_date'])->mapWithKeys(fn (Asistencia $a) => [$a->user_id.'|'.$a->shift_date->toDateString() => true]);
                $sinMarcar = $empezadas->filter(fn (Programacion $p) => collect([$p->driver_id, ...$p->ayudantes->pluck('id')->all()])
                    ->contains(fn ($persona) => ! isset($enDestino[$persona.'|'.$p->date->toDateString()])));

                if ($sinMarcar->isNotEmpty()) {
                    $fechas = $sinMarcar->map(fn (Programacion $p) => $p->date->toDateString())->unique()->sort()->values()->all();
                    $this->problema('asistencia', "Turno {$destino->name}", 'ya empezó y el personal no marcó en él', $fechas, 'Elija «Desde» a partir de mañana.');

                    return $fin(['ok' => false, 'errores' => ["El turno {$destino->name} ya empezó el ".self::resumenDeFechas($fechas).' y el personal de esas programaciones no registró asistencia en él: no se puede mover ahí. Elija «Desde» a partir de mañana.'],
                        'sugerencias' => [], 'avisos' => [], 'total' => $filas->count(), 'filas' => $filas]);
                }
            }
        }

        if ($d['rol'] === 'turno') {
            // Si alguien ya marcó asistencia en el turno actual, cambiar el turno dejaría su asistencia en otro turno.
            $conAsistencia = $filas->filter(fn (Programacion $p) => collect([$p->driver_id, ...$p->ayudantes->pluck('id')->all()])
                ->contains(fn ($persona) => $marco((int) $persona, $p)));

            if ($conAsistencia->isNotEmpty()) {
                $fechas = $conAsistencia->map(fn (Programacion $p) => $p->date->toDateString())->unique()->sort()->values()->all();

                $this->problema('asistencia', 'Cambio de turno', 'el personal ya registró asistencia en el turno actual', $fechas, 'Elija «Desde» a partir del día siguiente.');

                return $fin(['ok' => false, 'errores' => ['El personal ya registró asistencia en el turno actual el '.self::resumenDeFechas($fechas).': ese día no se puede cambiar el turno. Elija «Desde» a partir del día siguiente.'],
                    'sugerencias' => [], 'avisos' => [], 'total' => $filas->count(), 'filas' => $filas]);
            }
        }

        // Una solicitud por cada combinación distinta de turno/zona/vehículo/personas: sus fechas viajan juntas.
        $grupos = [];

        foreach ($filas as $p) {
            $ayudantes = $p->ayudantes->pluck('id')->map(fn ($i) => (int) $i)->all();
            $vehiculo = $p->vehicle_id;
            $conductor = $p->driver_id;
            $turnoId = $p->shift_id;

            if ($d['rol'] === 'turno') {
                $turnoId = (int) $d['entrante_id'];
            } elseif ($d['rol'] === 'conductor') {
                $conductor = (int) $d['entrante_id'];
            } elseif ($d['rol'] === 'vehiculo') {
                $vehiculo = (int) $d['entrante_id'];
            } else {
                $ayudantes = array_map(fn (int $i) => $i === (int) $d['saliente_id'] ? (int) $d['entrante_id'] : $i, $ayudantes);
            }

            $clave = implode('|', [$turnoId, $p->zone_id, $vehiculo, $conductor, implode(',', $ayudantes)]);
            $grupos[$clave]['solicitud'] ??= ['turno_id' => $turnoId, 'zona_id' => $p->zone_id, 'vehiculo_id' => $vehiculo, 'conductor_id' => $conductor, 'ayudantes' => $ayudantes, 'fechas' => [], 'ignorar_ids' => [],
                'excluir' => in_array($d['rol'], ['conductor', 'ayudante'], true) ? [(int) $d['saliente_id']] : []];
            $grupos[$clave]['solicitud']['fechas'][] = $p->date->toDateString();
            $grupos[$clave]['solicitud']['ignorar_ids'][] = $p->id;
        }

        $revision = $this->revisar(array_map(fn (array $g) => $g['solicitud'], array_values($grupos)), false);

        $avisos = $revision['avisos'];
        if ($d['desde'] < today()->toDateString()) {
            $avisos[] = 'Solo se reemplazan las programaciones desde hoy en adelante.';
        }

        if ($d['desde'] < today()->toDateString()) {
            $this->nota('reemplazo', 'Solo se reemplazan las programaciones desde hoy en adelante');
        }

        return ['ok' => $revision['ok'], 'errores' => $revision['errores'], 'sugerencias' => $revision['sugerencias'], 'avisos' => $avisos, 'total' => $filas->count(), 'filas' => $filas,
            'problemas' => $revision['problemas'], 'notas' => $revision['notas']];
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
            // Lo primero que se lee son las programaciones con bloqueo: así las lecturas siguientes parten de lo ya confirmado.
            $filas = $this->afectadasPorReemplazo($d, true);
            $ayudantes = DB::table('programming_helpers')->whereIn('programming_id', $filas->pluck('id'))->lockForUpdate()->pluck('user_id');

            $personas = $filas->pluck('driver_id')->merge($ayudantes);
            $vehiculos = $filas->pluck('vehicle_id');

            if ($d['rol'] === 'vehiculo') {
                $vehiculos = $vehiculos->push((int) $d['entrante_id']);
            } elseif ($d['rol'] !== 'turno') {
                $personas = $personas->push((int) $d['entrante_id']);
            }

            $this->bloquear($personas->filter()->unique()->sort()->values()->all(), $vehiculos->filter()->unique()->all());

            $revision = $this->revisarReemplazo($d);

            if (! $revision['ok']) {
                throw ValidationException::withMessages(['programacion' => $revision['errores']]);
            }

            $this->ultimoLote = (string) Str::uuid();
            $tipo = $d['rol'];
            $antes = Cambio::modeloDe($tipo, (int) $d['saliente_id']);
            $despues = Cambio::modeloDe($tipo, (int) $d['entrante_id']);
            $motivo = ['id' => $d['motivo_id'] ?? null, 'descripcion' => $d['descripcion'] ?? null];

            foreach ($revision['filas'] as $p) {
                $estado = ['status' => Programacion::REPROGRAMADO];

                if ($tipo === 'turno') {
                    $p->update($estado);
                    $p->moverATurno((int) $d['entrante_id']);
                } elseif ($tipo === 'conductor') {
                    $p->update($estado + ['driver_id' => (int) $d['entrante_id']]);
                } elseif ($tipo === 'vehiculo') {
                    $p->update($estado + ['vehicle_id' => (int) $d['entrante_id']]);
                } else {
                    $anteriores = $p->ayudantes->map->nombre_completo->implode(', ');
                    DB::table('programming_helpers')->where('programming_id', $p->id)->where('user_id', (int) $d['saliente_id'])->update(['user_id' => (int) $d['entrante_id']]);
                    $despuesDeAyudantes = $p->ayudantes()->get()->map->nombre_completo->implode(', ');
                    Bitacora::registrar('modificado', $p, ['ayudantes' => $anteriores], ['ayudantes' => $despuesDeAyudantes]);
                    $p->update($estado);
                }

                Cambio::registrar($p, $tipo, $antes, $despues, $motivo, $this->ultimoLote, $d['usuario_id'] ?? null, Cambio::MASIVO);
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
