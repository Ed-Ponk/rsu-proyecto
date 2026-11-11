<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\GrupoPersonal;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\Recorrido;

/**
 * Reglas y formato de datos de la app móvil del conductor (Avance 6).
 * Las fechas van como Y-m-d, las horas como H:i y los momentos exactos en ISO 8601 con zona horaria.
 */
class ServicioConductor
{
    private const RELACIONES = ['zona', 'turno', 'vehiculo', 'grupo', 'ayudantes', 'recorrido', 'conductor'];

    /** Programaciones del conductor (como conductor de la unidad) que todavía no terminaron, de la más próxima a la más lejana. */
    public function proximas(Personal $conductor, int $limite = 60)
    {
        return Programacion::with(self::RELACIONES)
            ->where('driver_id', $conductor->id)
            ->whereDate('date', '>=', today()->subDay())   // el nocturno de ayer puede seguir en curso
            ->orderBy('date')
            ->get()
            ->filter(fn (Programacion $p) => $p->recorrido?->enCurso() || ($p->esProgramado() && ! $p->haTerminado()))
            ->sortBy(fn (Programacion $p) => $p->inicio()->timestamp)
            ->take($limite)
            ->values();
    }

    /**
     * La programación «de hoy»: la del recorrido en curso; si no hay, la del turno en curso o la siguiente que empieza hoy.
     * Un turno nocturno de ayer cuenta mientras no termine.
     */
    public function deHoy(Personal $conductor): ?Programacion
    {
        $enCurso = Recorrido::activos()->where('driver_id', $conductor->id)->with(['programacion' => fn ($q) => $q->with(self::RELACIONES)])->first();

        if ($enCurso) {
            return $enCurso->programacion;
        }

        return Programacion::with(self::RELACIONES)
            ->where('driver_id', $conductor->id)
            ->whereIn('date', [today()->subDay()->toDateString(), today()->toDateString()])
            ->get()
            ->filter(fn (Programacion $p) => ! $p->haTerminado())
            ->sortBy(fn (Programacion $p) => $p->inicio()->timestamp)
            ->first();
    }

    /** Asistencia de cada integrante en el turno de la programación (la del conductor primero). */
    public function asistencia(Programacion $p): array
    {
        $presentes = Asistencia::presentesEnTurno($p->date->toDateString(), (int) $p->shift_id)
            ->whereIn('user_id', [$p->driver_id, ...$p->ayudantes->pluck('id')->all()])
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $fila = fn (Personal $persona, string $rol) => [
            'id' => $persona->id,
            'nombre' => $persona->nombre_completo,
            'rol' => $rol,
            'presente' => in_array($persona->id, $presentes, true),
        ];

        return [
            $fila($p->conductor, 'conductor'),
            ...$p->ayudantes->map(fn (Personal $a) => $fila($a, 'ayudante'))->all(),
        ];
    }

    /** ¿Puede iniciar el recorrido ahora? Devuelve [bool, motivo si no puede]. */
    public function puedeIniciar(Programacion $p, ?array $asistencia = null): array
    {
        $asistencia ??= $this->asistencia($p);
        $antes = (int) config('proyecto.recorridos.minutos_antes_de_iniciar', 30);

        return match (true) {
            $p->recorrido !== null => [false, $p->recorrido->enCurso() ? 'El recorrido ya está en curso.' : 'El recorrido de esta programación ya fue completado.'],
            ! $p->esProgramado() => [false, 'Esta programación ya fue finalizada.'],
            $p->haTerminado() => [false, 'El turno de esta programación ya terminó.'],
            now()->lt($p->inicio()->subMinutes($antes)) => [false, 'Podrá iniciar el recorrido desde las '.$p->inicio()->subMinutes($antes)->format('H:i').' ('.$p->inicio()->translatedFormat('d/m').').'],
            collect($asistencia)->contains('presente', false) => [false, 'Todo el personal debe registrar su asistencia antes de iniciar el recorrido.'],
            default => [true, null],
        };
    }

    /** Tarjeta de la lista «Mis programaciones». */
    public function resumen(Programacion $p): array
    {
        return [
            'id' => $p->id,
            'fecha' => $p->date->toDateString(),
            'estado' => $p->status,
            'zona' => ['id' => $p->zone_id, 'nombre' => $p->zona?->name],
            'vehiculo' => $this->vehiculo($p),
            'turno' => [
                'id' => $p->shift_id,
                'nombre' => $p->turno?->name,
                'hora_inicio' => substr((string) $p->turno?->time_start, 0, 5),
                'hora_fin' => substr((string) $p->turno?->time_end, 0, 5),
            ],
            'inicio' => $p->inicio()->toIso8601String(),
            'fin' => $p->fin()->toIso8601String(),
            'dias' => $p->grupo ? array_map(fn (int $d) => GrupoPersonal::DIAS[$d] ?? (string) $d, $p->grupo->dias_ordenados) : [],
            'recorrido' => $p->recorrido ? $this->recorridoBreve($p->recorrido) : null,
        ];
    }

    /** Pantalla «Programación de hoy»: datos del turno, asistencia y si se puede iniciar. */
    public function detalle(Programacion $p): array
    {
        $asistencia = $this->asistencia($p);
        [$puede, $motivo] = $this->puedeIniciar($p, $asistencia);

        return $this->resumen($p) + [
            'ayudantes' => $p->ayudantes->map(fn (Personal $a) => ['id' => $a->id, 'nombre' => $a->nombre_completo])->values()->all(),
            'asistencia' => $asistencia,
            'todos_presentes' => ! collect($asistencia)->contains('presente', false),
            'puede_iniciar' => $puede,
            'motivo' => $motivo,
        ];
    }

    public function recorridoBreve(Recorrido $r): array
    {
        return [
            'id' => $r->id,
            'estado' => $r->status,
            'estado_texto' => Recorrido::ESTADOS[$r->status] ?? $r->status,
            'inicio' => $r->started_at->toIso8601String(),
            'fin' => $r->ended_at?->toIso8601String(),
        ];
    }

    /** Fila del historial de recorridos. */
    public function recorridoResumen(Recorrido $r): array
    {
        $p = $r->programacion;

        return $this->recorridoBreve($r) + [
            'programacion_id' => $p->id,
            'fecha' => $p->date->toDateString(),
            'zona' => ['id' => $p->zone_id, 'nombre' => $p->zona?->name],
            'vehiculo' => $this->vehiculo($p),
            'turno' => ['nombre' => $p->turno?->name, 'hora_inicio' => substr((string) $p->turno?->time_start, 0, 5), 'hora_fin' => substr((string) $p->turno?->time_end, 0, 5)],
        ];
    }

    /** Detalle de un recorrido: trayectoria completa, zona y observación. */
    public function recorridoDetalle(Recorrido $r): array
    {
        $p = $r->programacion;

        return $this->recorridoResumen($r) + [
            'ayudantes' => $p->ayudantes->map(fn (Personal $a) => ['id' => $a->id, 'nombre' => $a->nombre_completo])->values()->all(),
            'observacion' => $r->observation,
            'zona_vertices' => $p->zona ? $p->zona->vertices() : [],
            'puntos' => $r->puntos->map(fn ($pt) => [
                'lat' => $pt->latitude,
                'lng' => $pt->longitude,
                'momento' => $pt->recorded_at->toIso8601String(),
            ])->all(),
            'segundos_entre_puntos' => (int) config('proyecto.recorridos.segundos_entre_puntos', 5),
        ];
    }

    public function perfil(Personal $c): array
    {
        return [
            'id' => $c->id,
            'nombre' => $c->name,
            'apellidos' => $c->last_name,
            'nombre_completo' => $c->nombre_completo,
            'dni' => $c->dni,
            'correo' => $c->email,
            'telefono' => $c->phone,
            'licencia' => $c->license,
            'foto' => $c->url_foto,
        ];
    }

    private function vehiculo(Programacion $p): array
    {
        return ['id' => $p->vehicle_id, 'nombre' => $p->vehiculo?->name, 'placa' => $p->vehiculo?->plate, 'codigo' => $p->vehiculo?->code];
    }
}
