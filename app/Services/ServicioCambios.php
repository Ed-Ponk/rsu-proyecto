<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Cambio;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\Turno;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cambios sobre programaciones ya creadas (Avance 04): turno, vehículo, conductor y ayudantes.
 *
 * - `modificar`: una programación; cada cambio lleva su motivo (uno predefinido o un texto) y queda registrado.
 * - `masivo`: un rango de fechas (y zona) de una sola vez; ver ReglasProgramacion::reemplazar.
 * - `eliminar`: deshace cambios y restablece los valores que tenía la programación antes de cada uno.
 *
 * Toda programación cambiada pasa a «Reprogramado»; si se deshacen todos sus cambios vuelve a «Programado».
 */
class ServicioCambios
{
    public function __construct(private readonly ReglasProgramacion $reglas) {}

    /** Nombre de cada campo de motivo, por ejemplo «ayudante_2» para el segundo ayudante. */
    public const CAMPO_TURNO = 'turno';

    public const CAMPO_VEHICULO = 'vehiculo';

    public const CAMPO_CONDUCTOR = 'conductor';

    /* ------------------------------------------------------------------ modificar una programación */

    /**
     * Revisa (sin guardar) cómo quedaría la programación con los datos nuevos.
     *
     * @param  array{turno_id?: int|null, vehiculo_id: int, conductor_id: int, ayudantes: list<int>}  $datos
     */
    public function revisar(Programacion $programacion, array $datos): array
    {
        return $this->reglas->revisar($this->solicitudes([$this->estado($programacion, $datos)]));
    }

    /**
     * Aplica los cambios de una programación. Devuelve cuántos cambios se registraron (0 si solo cambiaron las observaciones).
     *
     * @param  array{turno_id?: int|null, vehiculo_id: int, conductor_id: int, ayudantes: list<int>, notas?: string|null, motivos?: array<string, array>}  $datos
     *
     * @throws ValidationException
     */
    public function modificar(Programacion $programacion, array $datos, ?int $usuarioId = null): int
    {
        return $this->reglas->sinChoques(fn () => DB::transaction(function () use ($programacion, $datos, $usuarioId) {
            $programacion = Programacion::lockForUpdate()->findOrFail($programacion->id);
            $this->exigirModificable($programacion);

            $estado = $this->estado($programacion, $datos);
            $actuales = $programacion->ayudantes()->pluck('users.id')->map(fn ($i) => (int) $i)->all();
            $cambios = $this->diferencias($programacion, $actuales, $estado);

            $this->reglas->bloquear(
                collect([$programacion->driver_id, $estado['conductor'], ...$actuales, ...$estado['ayudantes']])->filter()->unique()->sort()->values()->all(),
                collect([$programacion->vehicle_id, $estado['vehiculo']])->unique()->all(),
            );

            // Cada cambio necesita su motivo.
            foreach ($cambios as $c) {
                $motivo = $datos['motivos'][$c['campo']] ?? [];
                if (blank($motivo['motivo_id'] ?? null) && blank($motivo['descripcion'] ?? null)) {
                    throw ValidationException::withMessages(['motivos' => ["Indique el motivo del cambio de {$c['texto']}: elija uno de la lista o escríbalo."]]);
                }
            }

            // Quien ya registró asistencia en el turno de la programación está trabajando: no se lo puede quitar.
            $conAsistencia = $programacion->personasConAsistencia();
            $quitados = $conAsistencia->reject(fn (Personal $p) => in_array($p->id, [$estado['conductor'], ...$estado['ayudantes']], true));
            if ($quitados->isNotEmpty()) {
                throw ValidationException::withMessages(['programacion' => [$quitados->map->nombre_completo->implode(', ').' ya registró asistencia en este turno y no se puede quitar de la programación.']]);
            }

            // Tampoco se cambia el turno si alguien ya marcó en el actual: su asistencia quedaría en otro turno.
            if ($estado['turno'] !== (int) $programacion->shift_id && $conAsistencia->isNotEmpty()) {
                throw ValidationException::withMessages(['programacion' => [$conAsistencia->map->nombre_completo->implode(', ').' ya registró asistencia en el turno '.$programacion->turno?->name.': no se puede cambiar el turno de esta programación.']]);
            }

            // Si el turno ya empezó, quien entra tiene que haber marcado asistencia EN ESE turno (el final, si también se cambia).
            $entran = array_values(array_diff([$estado['conductor'], ...$estado['ayudantes']], [$programacion->driver_id, ...$actuales]));
            if ($entran && $this->reglas->exigeAsistencia($programacion, $estado['turno'])) {
                $presentes = $this->reglas->presentesEnTurno($entran, $programacion->date->toDateString(), $estado['turno']);
                $faltan = Personal::whereIn('users.id', array_diff($entran, $presentes))->get();
                if ($faltan->isNotEmpty()) {
                    $turno = Turno::find($estado['turno'])?->name;
                    throw ValidationException::withMessages(['programacion' => [$faltan->map->nombre_completo->implode(', ')." no registró asistencia en el turno {$turno}: el turno ya empezó y quien cubre tiene que estar presente en ese mismo turno."]]);
                }
            }

            $revision = $this->reglas->revisar($this->solicitudes([$estado]));
            if (! $revision['ok']) {
                throw ValidationException::withMessages(['programacion' => $revision['errores']]);
            }

            $antes = $programacion->ayudantes()->get()->map->nombre_completo->implode(', ');
            $notas = filled($datos['notas'] ?? null) ? trim($datos['notas']) : null;
            $campos = ['vehicle_id' => $estado['vehiculo'], 'driver_id' => $estado['conductor'], 'notes' => $notas];

            if ($cambios) {
                $campos['status'] = Programacion::REPROGRAMADO;
            }

            $turnoAnterior = (int) $programacion->shift_id;
            $programacion->update($campos);

            if ($estado['turno'] !== $turnoAnterior) {
                $programacion->moverATurno($estado['turno']);
            }

            if ($estado['ayudantes'] !== $actuales) {
                $programacion->asignarAyudantes($estado['ayudantes']);
                Bitacora::registrar('modificado', $programacion, ['ayudantes' => $antes], ['ayudantes' => $programacion->ayudantes()->get()->map->nombre_completo->implode(', ')]);
            }

            // Todos los cambios de una misma modificación comparten lote: se deshacen juntos y se validan sobre el estado final
            // (por ejemplo, volver a un vehículo grande y recuperar al ayudante que se quitó).
            $lote = (string) Str::uuid();
            foreach ($cambios as $c) {
                Cambio::registrar($programacion, $c['tipo'], $c['antes'], $c['despues'], $datos['motivos'][$c['campo']] ?? [], $lote, $usuarioId);
            }

            return count($cambios);
        }, 3));
    }

    /* ------------------------------------------------------------------ cambio masivo */

    /**
     * Cambio de turno, vehículo, conductor o ayudante en las programaciones pendientes de un rango (y zona).
     *
     * @return array{0: int, 1: string|null} programaciones cambiadas y lote con el que se registraron
     */
    public function masivo(array $datos, ?int $usuarioId = null): array
    {
        $total = $this->reglas->reemplazar($datos + ['usuario_id' => $usuarioId]);

        return [$total, $this->reglas->ultimoLote()];
    }

    public function revisarMasivo(array $datos): array
    {
        return $this->reglas->revisarReemplazo($datos);
    }

    /* ------------------------------------------------------------------ eliminar cambios y restablecer */

    /**
     * Elimina cambios y deja cada programación como estaba antes de ellos. Todo o nada: si una programación ya no se
     * puede restablecer (finalizó, ya pasó, hay un cambio posterior o el valor anterior ya no está libre) no se toca ninguna.
     *
     * @param  Collection<int, Cambio>  $cambios
     * @return int programaciones restablecidas
     *
     * @throws ValidationException
     */
    public function eliminar(Collection $cambios): int
    {
        if ($cambios->isEmpty()) {
            return 0;
        }

        return $this->reglas->sinChoques(fn () => DB::transaction(function () use ($cambios) {
            $ids = $cambios->pluck('programming_id')->unique()->sort()->values();
            $programaciones = Programacion::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            // Otra persona pudo eliminar ya estos cambios mientras se esperaba el bloqueo.
            if (Cambio::whereIn('id', $cambios->pluck('id'))->count() !== $cambios->count()) {
                throw ValidationException::withMessages(['cambio' => ['Este cambio ya fue eliminado por otra persona. Actualice la lista.']]);
            }

            $porProgramacion = $cambios->groupBy('programming_id');
            $estados = [];
            $personas = [];
            $vehiculos = [];

            foreach ($porProgramacion as $idProgramacion => $deEsta) {
                $programacion = $programaciones[$idProgramacion] ?? null;
                if (! $programacion) {
                    continue;
                }

                $dia = $programacion->date->format('d/m/Y');

                if (! $programacion->esProgramado()) {
                    throw ValidationException::withMessages(['cambio' => ["La programación del {$dia} ya fue finalizada: su cambio no se puede deshacer."]]);
                }

                if ($programacion->date->lt(today())) {
                    throw ValidationException::withMessages(['cambio' => ["La programación del {$dia} ya pasó su fecha: su cambio no se puede deshacer."]]);
                }

                $quitar = $deEsta->pluck('id')->all();
                $posteriores = Cambio::where('programming_id', $idProgramacion)->whereNotIn('id', $quitar)->get();
                $estado = [
                    'id' => $programacion->id, 'fecha' => $programacion->date->toDateString(), 'zona' => $programacion->zone_id, 'turno' => $programacion->shift_id,
                    'vehiculo' => $programacion->vehicle_id, 'conductor' => $programacion->driver_id,
                    'ayudantes' => $programacion->ayudantes()->pluck('users.id')->map(fn ($i) => (int) $i)->all(),
                ];

                // Del más reciente al más antiguo, para que cada valor anterior sea el que realmente había.
                foreach ($deEsta->sortByDesc('id') as $c) {
                    $despues = $posteriores->first(fn (Cambio $p) => $p->id > $c->id && $p->type === $c->type
                        && ($c->type !== Cambio::AYUDANTE || $p->old_id === $c->new_id || $p->new_id === $c->old_id));

                    if ($despues) {
                        throw ValidationException::withMessages(['cambio' => ["La programación del {$dia} tiene un cambio de {$c->tipo_texto} posterior a este: elimine primero el más reciente."]]);
                    }

                    $this->restablecer($estado, $c, $dia);
                }

                $estados[] = $estado;
                $personas = [...$personas, $programacion->driver_id, $estado['conductor'], ...$estado['ayudantes']];
                $vehiculos = [...$vehiculos, $programacion->vehicle_id, $estado['vehiculo']];
            }

            $this->reglas->bloquear(collect($personas)->filter()->unique()->sort()->values()->all(), collect($vehiculos)->filter()->unique()->all());

            $revision = $this->reglas->revisar($this->solicitudes($estados));
            if (! $revision['ok']) {
                $primeros = array_slice($revision['errores'], 0, 3);
                throw ValidationException::withMessages(['cambio' => ['No se puede restablecer el valor anterior: '.implode(' ', $primeros).(count($revision['errores']) > 3 ? ' (y '.(count($revision['errores']) - 3).' problema(s) más)' : '')]]);
            }

            foreach ($estados as $estado) {
                $programacion = $programaciones[$estado['id']];
                $actuales = $programacion->ayudantes()->pluck('users.id')->map(fn ($i) => (int) $i)->all();
                $antes = $programacion->ayudantes()->get()->map->nombre_completo->implode(', ');

                $programacion->update(['vehicle_id' => $estado['vehiculo'], 'driver_id' => $estado['conductor']]);

                if ($estado['turno'] !== (int) $programacion->shift_id) {
                    $programacion->moverATurno($estado['turno']);
                }

                if ($estado['ayudantes'] !== $actuales) {
                    $programacion->asignarAyudantes($estado['ayudantes']);
                    Bitacora::registrar('modificado', $programacion, ['ayudantes' => $antes], ['ayudantes' => $programacion->ayudantes()->get()->map->nombre_completo->implode(', ')]);
                }

                Cambio::whereIn('id', $porProgramacion[$estado['id']]->pluck('id'))->delete();
                $quedan = Cambio::where('programming_id', $programacion->id)->exists();
                $programacion->update(['status' => $quedan ? Programacion::REPROGRAMADO : Programacion::PROGRAMADO]);
            }

            return count($estados);
        }, 3));
    }

    /* ------------------------------------------------------------------ ayudas */

    /** Pone en `$estado` el valor que había antes del cambio `$c`, comprobando que el actual sea el del cambio. */
    private function restablecer(array &$estado, Cambio $c, string $dia): void
    {
        $desajuste = fn (string $que) => ValidationException::withMessages(['cambio' => ["En la programación del {$dia} el {$que} ya no es el que dejó este cambio ({$c->new_label}): no se puede restablecer."]]);

        switch ($c->type) {
            case Cambio::TURNO:
            case Cambio::VEHICULO:
            case Cambio::CONDUCTOR:
                $campo = $c->type;
                if ($estado[$campo] !== (int) $c->new_id) {
                    throw $desajuste($c->tipo_texto === 'Ocupante' ? 'ayudante' : Str::lower($c->tipo_texto));
                }
                $estado[$campo] = (int) $c->old_id;
                break;

            default:   // ayudante: cambiado por otro, quitado o agregado
                $indice = $c->new_id ? array_search((int) $c->new_id, $estado['ayudantes'], true) : false;

                if ($c->new_id && $indice === false) {
                    throw $desajuste('ayudante');
                }

                if ($c->new_id && $c->old_id) {
                    $estado['ayudantes'][$indice] = (int) $c->old_id;
                } elseif ($c->new_id) {
                    array_splice($estado['ayudantes'], $indice, 1);
                } else {
                    $estado['ayudantes'][] = (int) $c->old_id;
                }
        }
    }

    /** Estado final que se quiere para la programación, a partir de lo que llegó del formulario. */
    private function estado(Programacion $programacion, array $datos): array
    {
        return [
            'id' => $programacion->id,
            'fecha' => $programacion->date->toDateString(),
            'zona' => (int) $programacion->zone_id,
            'turno' => (int) ($datos['turno_id'] ?? $programacion->shift_id),
            'vehiculo' => (int) $datos['vehiculo_id'],
            'conductor' => (int) $datos['conductor_id'],
            'ayudantes' => array_values(array_map('intval', $datos['ayudantes'])),
        ];
    }

    /**
     * Cambios entre la programación actual y el estado nuevo.
     *
     * @return list<array{campo: string, tipo: string, texto: string, antes: mixed, despues: mixed}>
     */
    private function diferencias(Programacion $programacion, array $actuales, array $estado): array
    {
        $cambios = [];
        $uno = fn (string $campo, string $tipo, string $texto, int $antes, int $despues) => $antes === $despues ? null : [
            'campo' => $campo, 'tipo' => $tipo, 'texto' => $texto,
            'antes' => Cambio::modeloDe($tipo, $antes), 'despues' => Cambio::modeloDe($tipo, $despues),
        ];

        $cambios[] = $uno(self::CAMPO_TURNO, Cambio::TURNO, 'turno', (int) $programacion->shift_id, $estado['turno']);
        $cambios[] = $uno(self::CAMPO_VEHICULO, Cambio::VEHICULO, 'vehículo', (int) $programacion->vehicle_id, $estado['vehiculo']);
        $cambios[] = $uno(self::CAMPO_CONDUCTOR, Cambio::CONDUCTOR, 'conductor', (int) $programacion->driver_id, $estado['conductor']);

        for ($i = 0, $n = max(count($actuales), count($estado['ayudantes'])); $i < $n; $i++) {
            $antes = $actuales[$i] ?? null;
            $despues = $estado['ayudantes'][$i] ?? null;

            if ($antes === $despues) {
                continue;
            }

            $cambios[] = [
                'campo' => 'ayudante_'.($i + 1), 'tipo' => Cambio::AYUDANTE, 'texto' => 'ayudante '.($i + 1),
                'antes' => $antes ? Cambio::modeloDe(Cambio::AYUDANTE, $antes) : null,
                'despues' => $despues ? Cambio::modeloDe(Cambio::AYUDANTE, $despues) : null,
            ];
        }

        return array_values(array_filter($cambios));
    }

    /** Una solicitud de revisión por cada combinación distinta de recursos, con sus fechas (las programaciones que se ignoran). */
    private function solicitudes(array $estados): array
    {
        $grupos = [];

        foreach ($estados as $e) {
            $clave = implode('|', [$e['turno'], $e['zona'], $e['vehiculo'], $e['conductor'], implode(',', $e['ayudantes'])]);
            $grupos[$clave] ??= [
                'turno_id' => $e['turno'], 'zona_id' => $e['zona'], 'vehiculo_id' => $e['vehiculo'], 'conductor_id' => $e['conductor'],
                'ayudantes' => $e['ayudantes'], 'fechas' => [], 'ignorar_ids' => [],
            ];
            $grupos[$clave]['fechas'][] = $e['fecha'];
            $grupos[$clave]['ignorar_ids'][] = $e['id'];
        }

        return array_values($grupos);
    }

    private function exigirModificable(Programacion $programacion): void
    {
        if (! $programacion->esProgramado()) {
            throw ValidationException::withMessages(['programacion' => ['Esta programación ya fue finalizada y no se puede modificar.']]);
        }

        if ($programacion->date->lt(today())) {
            throw ValidationException::withMessages(['programacion' => ['Esta programación ya pasó su fecha: solo se puede finalizar o eliminar.']]);
        }
    }
}
