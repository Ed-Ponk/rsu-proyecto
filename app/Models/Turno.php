<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Turno de trabajo (Mañana, Tarde, Noche). Si la hora de fin es menor o igual a la de inicio, cruza la medianoche. */
class Turno extends Model
{
    protected $table = 'schedules';

    protected $fillable = ['name', 'time_start', 'time_end', 'description'];

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

    /** Momento exacto en que empieza el turno de la jornada indicada (día en que empieza). */
    public function inicioDe(string $jornada): Carbon
    {
        return Carbon::parse($jornada.' '.substr($this->time_start, 0, 5));
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
    public function minutosDeTardanza(string $jornada, Carbon $entrada): int
    {
        $minutos = (int) floor($this->inicioDe($jornada)->diffInSeconds($entrada, false) / 60);

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
