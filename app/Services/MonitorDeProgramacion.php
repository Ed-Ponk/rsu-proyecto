<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\Vacacion;
use Illuminate\Support\Collection;

/**
 * Estado de las programaciones de un día para el dashboard: quién está cumpliendo y quién falta.
 * Una programación está «completa» si todo su personal puede y debe estar trabajando ese día.
 */
class MonitorDeProgramacion
{
    /**
     * @return Collection<int, array{programacion: Programacion, personal: list<array{id: int, nombre: string, rol: string, estado: string, motivo: ?string}>, faltantes: int, completa: bool}>
     */
    public function delDia(string $fecha, ?int $turnoId = null, ?int $zonaId = null): Collection
    {
        $programaciones = Programacion::with(['zona', 'turno', 'grupo', 'vehiculo', 'conductor.tipo', 'ayudantes.tipo'])
            ->where('date', $fecha)
            ->when($turnoId, fn ($q) => $q->where('shift_id', $turnoId))
            ->when($zonaId, fn ($q) => $q->where('zone_id', $zonaId))
            ->orderBy('zone_id')->orderBy('shift_id')->get();

        return $this->evaluar($programaciones, $fecha);
    }

    /** Estado de una sola programación (para el editor). @return array{programacion: Programacion, personal: list<array>, faltantes: int, completa: bool} */
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

        $asistencias = Asistencia::where('shift_date', $fecha)->whereIn('user_id', $ids)->get(['user_id', 'status'])
            ->groupBy('user_id')->map(fn (Collection $l) => $l->contains('status', Asistencia::PRESENTE) ? Asistencia::PRESENTE : Asistencia::AUSENTE);

        return $programaciones->map(function (Programacion $p) use ($vacaciones, $conContrato, $asistencias) {
            $personal = collect([['persona' => $p->conductor, 'rol' => 'conductor'], ...$p->ayudantes->map(fn (Personal $a) => ['persona' => $a, 'rol' => 'ayudante'])->all()])
                ->filter(fn (array $x) => $x['persona'])
                ->map(function (array $x) use ($p, $vacaciones, $conContrato, $asistencias) {
                    /** @var Personal $persona */
                    $persona = $x['persona'];
                    $motivo = $this->motivoDeFalta($p, $persona, $vacaciones, $conContrato, $asistencias);

                    return ['id' => $persona->id, 'nombre' => $persona->nombre_completo, 'rol' => $x['rol'], 'estado' => $motivo ? 'faltante' : 'ok', 'motivo' => $motivo,
                        'presente' => ($asistencias[$persona->id] ?? null) === Asistencia::PRESENTE];
                })->values()->all();

            $faltantes = count(array_filter($personal, fn (array $x) => $x['estado'] === 'faltante'));

            return ['programacion' => $p, 'personal' => $personal, 'faltantes' => $faltantes, 'completa' => $faltantes === 0];
        });
    }

    private function motivoDeFalta(Programacion $p, Personal $persona, Collection $vacaciones, Collection $conContrato, Collection $asistencias): ?string
    {
        if (! $persona->status) {
            return 'Está inactivo';
        }

        if ($vacaciones->has($persona->id)) {
            return 'Está de vacaciones';
        }

        if (! $conContrato->has($persona->id)) {
            return 'No tiene contrato vigente';
        }

        $marcada = $asistencias[$persona->id] ?? null;

        if ($marcada === Asistencia::AUSENTE) {
            return 'Faltó al trabajo';
        }

        if ($marcada === Asistencia::PRESENTE || $p->status === Programacion::FINALIZADO) {
            return null;
        }

        // Sin marcación: solo se considera falta cuando el turno ya empezó (hoy se espera una hora para no marcar a quien aún no llega).
        $hoy = today();
        $inicio = $p->minutoDeInicio();
        $yaEmpezo = $p->date->lt($hoy) || ($p->date->eq($hoy) && now()->hour * 60 + now()->minute >= $inicio + 60);

        return $yaEmpezo ? 'No registró asistencia' : null;
    }
}
