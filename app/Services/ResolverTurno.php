<?php

namespace App\Services;

use App\Models\Turno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Averigua a qué turno (tabla `schedules`) pertenece una hora del día. */
class ResolverTurno
{
    /** @return Collection<int, Turno> */
    public static function todos(): Collection
    {
        return Turno::orderBy('time_start')->get();
    }

    public static function paraHora(string|Carbon $hora): ?Turno
    {
        $minuto = self::aMinutos($hora);

        return self::todos()->first(function (Turno $t) use ($minuto) {
            $inicio = self::aMinutos($t->time_start);
            $fin = self::aMinutos($t->time_end);

            // Un turno nocturno termina "antes" de lo que empieza: cruza la medianoche.
            return $inicio < $fin
                ? $minuto >= $inicio && $minuto < $fin
                : $minuto >= $inicio || $minuto < $fin;
        });
    }

    private static function aMinutos(string|Carbon $hora): int
    {
        if ($hora instanceof Carbon) {
            return $hora->hour * 60 + $hora->minute;
        }

        [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));

        return $h * 60 + $m;
    }
}
