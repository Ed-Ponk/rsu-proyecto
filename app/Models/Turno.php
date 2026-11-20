<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Turno de trabajo (Mañana, Tarde, Noche). Si la hora de fin es menor o igual a la de inicio, cruza la medianoche. */
class Turno extends Model
{
    use Auditable;

    protected $table = 'schedules';

    protected $fillable = ['name', 'time_start', 'time_end', 'description'];

    /**
     * Tramos del día (HH:MM - HH:MM) que no cubre ningún turno. Si hay alguno, en esas horas nadie puede marcar.
     *
     * @return array<int, string>
     */
    public static function huecos(): array
    {
        $cubierto = array_fill(0, 1440, false);

        foreach (static::all(['time_start', 'time_end']) as $turno) {
            $inicio = ((int) substr($turno->time_start, 0, 2)) * 60 + (int) substr($turno->time_start, 3, 2);
            $fin = ((int) substr($turno->time_end, 0, 2)) * 60 + (int) substr($turno->time_end, 3, 2);

            for ($m = $inicio; $m !== $fin; $m = ($m + 1) % 1440) {
                $cubierto[$m] = true;
            }
        }

        $formato = fn (int $minuto) => sprintf('%02d:%02d', intdiv($minuto % 1440, 60), $minuto % 60);
        $huecos = [];
        $desde = null;

        for ($m = 0; $m <= 1440; $m++) {
            $libre = $m < 1440 && ! $cubierto[$m];

            if ($libre && $desde === null) {
                $desde = $m;
            } elseif (! $libre && $desde !== null) {
                $huecos[] = $formato($desde).' - '.$formato($m);
                $desde = null;
            }
        }

        return $huecos;
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'schedule_id');
    }

    public function getRangoAttribute(): string
    {
        return substr($this->time_start, 0, 5).' - '.substr($this->time_end, 0, 5);
    }

    public function getEtiquetaAttribute(): string
    {
        return "{$this->name} ({$this->rango})";
    }

    public function cruzaMedianoche(): bool
    {
        return substr($this->time_end, 0, 5) <= substr($this->time_start, 0, 5);
    }

    /**
     * Momento exacto en que empieza el turno de la jornada indicada (día en que empieza).
     * $hora permite usar la hora de inicio que el turno tenía cuando se hizo la marcación (HH:MM).
     */
    public function inicioDe(string $jornada, ?string $hora = null): Carbon
    {
        return Carbon::parse($jornada.' '.substr($hora ?? $this->time_start, 0, 5));
    }

    /** Momento exacto en que termina: al día siguiente si el turno cruza la medianoche. */
    public function finDe(string $jornada): Carbon
    {
        $fin = Carbon::parse($jornada.' '.substr($this->time_end, 0, 5));

        return $this->cruzaMedianoche() ? $fin->addDay() : $fin;
    }

    /**
     * Minutos de tardanza de una entrada: lo que pasó desde el inicio del turno, pero solo si supera la tolerancia
     * (dentro de la tolerancia, o si llegó antes, es 0). Se cuenta desde el inicio, no desde el fin de la tolerancia.
     */
    public function minutosDeTardanza(string $jornada, Carbon $entrada, ?string $inicio = null): int
    {
        $minutos = (int) floor($this->inicioDe($jornada, $inicio)->diffInSeconds($entrada, false) / 60);

        return $minutos > (int) config('proyecto.asistencia.minutos_tolerancia', 10) ? $minutos : 0;
    }

    /** Día en que empezó el turno que está en curso en ese momento (la madrugada del turno noche es del día anterior). */
    public function jornadaPara(Carbon $momento): string
    {
        $antesDelFin = $momento->format('H:i') < substr($this->time_end, 0, 5);

        return $this->cruzaMedianoche() && $antesDelFin
            ? $momento->copy()->subDay()->toDateString()
            : $momento->toDateString();
    }
}
