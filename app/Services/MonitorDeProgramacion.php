<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\Vacacion;
use Illuminate\Support\Collection;

/**
 * Estado de las programaciones de un día para el dashboard: quién está trabajando y quién falta.
 *
 * Cada persona queda en uno de tres estados, medidos en el TURNO de la programación (la asistencia de otro turno no cuenta):
 *  - «presente»: marcó asistencia en ese turno.
 *  - «faltante»: no puede o no vino (inactiva, de vacaciones, sin contrato, marcada ausente, o el turno empezó y no marcó).
 *  - «pendiente»: todavía no marca porque su turno no ha empezado.
 *
 * Una programación está «completa» solo cuando TODO su personal marcó asistencia; mientras tanto es «incompleta».
 */
class MonitorDeProgramacion
{
    public const PRESENTE = 'presente';

    public const FALTANTE = 'faltante';

    public const PENDIENTE = 'pendiente';

    /**
     * @return Collection<int, array{programacion: Programacion, personal: list<array{id: int, nombre: string, rol: string, estado: string, motivo: ?string, presente: bool}>, presentes: int, faltantes: int, pendientes: int, completa: bool}>
     */
    public function delDia(string $fecha, ?int $turnoId = null, ?int $zonaId = null): Collection
    {
        $consulta = fn (string $dia) => Programacion::with(['zona', 'turno', 'grupo', 'vehiculo', 'conductor.tipo', 'ayudantes.tipo'])
            ->where('date', $dia)
            ->when($turnoId, fn ($q) => $q->where('shift_id', $turnoId))
            ->when($zonaId, fn ($q) => $q->where('zone_id', $zonaId))
            ->orderBy('zone_id')->orderBy('shift_id')->get();

        $resultado = $this->evaluar($consulta($fecha), $fecha);

        // Hoy también se vigila el turno de noche de AYER que sigue en curso después de medianoche (hasta las 06:00).
        if ($fecha === today()->toDateString()) {
            $ayer = today()->subDay()->toDateString();
            $enCurso = $consulta($ayer)->filter(fn (Programacion $p) => $p->turno?->cruzaMedianoche() && ! $p->haTerminado())->values();
            $resultado = $resultado->concat($this->evaluar($enCurso, $ayer));
        }

        return $resultado->values();
    }

    /** Estado de una sola programación (para el editor). @return array{programacion: Programacion, personal: list<array>, presentes: int, faltantes: int, pendientes: int, completa: bool} */
    public function deProgramacion(Programacion $programacion): array
    {
        $programacion->loadMissing(['zona', 'turno', 'grupo', 'vehiculo', 'conductor.tipo', 'ayudantes.tipo']);

        return $this->evaluar(collect([$programacion]), $programacion->date->toDateString())->first();
    }

    private function evaluar(Collection $programaciones, string $fecha): Collection
    {
        if ($programaciones->isEmpty()) {
            return collect();
        }

        $ids = $programaciones->flatMap(fn (Programacion $p) => [$p->driver_id, ...$p->ayudantes->pluck('id')->all()])->unique()->values()->all();

        $vacaciones = Vacacion::where('status', Vacacion::APROBADA)->whereIn('user_id', $ids)
            ->where('start_date', '<=', $fecha)->where('end_date', '>=', $fecha)->pluck('user_id')->flip();

        $conContrato = Contrato::where('is_active', true)->whereIn('user_id', $ids)
            ->where('start_date', '<=', $fecha)->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $fecha))
            ->pluck('user_id')->flip();

        // Marcaciones de la jornada agrupadas por persona y TURNO: «usuario|turno» => presente o ausente.
        $asistencias = Asistencia::where('shift_date', $fecha)->whereIn('user_id', $ids)->get(['user_id', 'schedule_id', 'status'])
            ->groupBy(fn (Asistencia $a) => $a->user_id.'|'.$a->schedule_id)
            ->map(fn (Collection $l) => $l->contains('status', Asistencia::PRESENTE) ? Asistencia::PRESENTE : Asistencia::AUSENTE);

        return $programaciones->map(function (Programacion $p) use ($vacaciones, $conContrato, $asistencias) {
            $personal = collect([['persona' => $p->conductor, 'rol' => 'conductor'], ...$p->ayudantes->map(fn (Personal $a) => ['persona' => $a, 'rol' => 'ayudante'])->all()])
                ->filter(fn (array $x) => $x['persona'])
                ->map(function (array $x) use ($p, $vacaciones, $conContrato, $asistencias) {
                    /** @var Personal $persona */
                    $persona = $x['persona'];
                    $marcada = $asistencias[$persona->id.'|'.$p->shift_id] ?? null;
                    [$estado, $motivo] = $marcada === Asistencia::PRESENTE
                        ? [self::PRESENTE, null]
                        : $this->sinAsistencia($p, $persona, $marcada, $vacaciones, $conContrato);

                    return ['id' => $persona->id, 'nombre' => $persona->nombre_completo, 'rol' => $x['rol'], 'estado' => $estado, 'motivo' => $motivo,
                        'presente' => $estado === self::PRESENTE];
                })->values()->all();

            $cuenta = fn (string $estado) => count(array_filter($personal, fn (array $x) => $x['estado'] === $estado));
            $presentes = $cuenta(self::PRESENTE);

            return [
                'programacion' => $p,
                'personal' => $personal,
                'presentes' => $presentes,
                'faltantes' => $cuenta(self::FALTANTE),
                'pendientes' => $cuenta(self::PENDIENTE),
                'completa' => $personal !== [] && $presentes === count($personal),
            ];
        });
    }

    /**
     * Estado de quien no marcó asistencia en el turno: faltante (con el motivo) o pendiente si su turno aún no empieza.
     *
     * @return array{0: string, 1: string}
     */
    private function sinAsistencia(Programacion $p, Personal $persona, ?string $marcada, Collection $vacaciones, Collection $conContrato): array
    {
        $motivo = match (true) {
            ! $persona->status => 'Está inactivo',
            $vacaciones->has($persona->id) => 'Está de vacaciones',
            ! $conContrato->has($persona->id) => 'No tiene contrato vigente',
            $marcada === Asistencia::AUSENTE => "Faltó al turno {$p->turno?->name}",
            default => null,
        };

        if ($motivo) {
            return [self::FALTANTE, $motivo];
        }

        // Sin marcación: es falta cuando el turno ya empezó (hoy se espera una hora para no marcar a quien aún no llega).
        // Una hora de gracia después del inicio real del turno (en su propia fecha: la noche de ayer ya empezó hace horas).
        $yaEmpezo = $p->status === Programacion::FINALIZADO
            || now()->gte($p->inicio()->addHour());

        return $yaEmpezo
            ? [self::FALTANTE, "No registró asistencia en el turno {$p->turno?->name}"]
            : [self::PENDIENTE, "Aún no registra asistencia en el turno {$p->turno?->name}"];
    }
}
