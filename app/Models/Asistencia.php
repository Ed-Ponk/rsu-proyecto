<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Asistencia extends Model
{
    use Auditable;

    protected $table = 'attendances';

    public const PRESENTE = 'presente';

    public const AUSENTE = 'ausente';

    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    protected $fillable = [
        'user_id', 'date', 'time', 'schedule_id', 'shift_date', 'shift_start', 'type', 'late_minutes', 'status', 'source', 'notes', 'registered_by',
    ];

    protected function casts(): array
    {
        // "date:Y-m-d": se guarda solo el día, así las consultas por rango comparan la columna directamente (y usan su índice).
        return ['date' => 'date:Y-m-d', 'shift_date' => 'date:Y-m-d', 'late_minutes' => 'integer'];
    }

    /** Duración legible de una tardanza: 45 → "45 min", 60 → "1 h", 90 → "1 h 30 min", 120 → "2 h". */
    public static function textoDeDuracion(int $minutos): string
    {
        if ($minutos < 60) {
            return "{$minutos} min";
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto ? "{$horas} h {$resto} min" : "{$horas} h";
    }

    /** Momento real de la marcación (fecha calendario + hora). */
    public function momento(): Carbon
    {
        return Carbon::parse($this->date->toDateString().' '.$this->time);
    }

    /** Marcaciones (presente) de una persona en un turno concreto: mismo turno y mismo día de inicio. */
    public function scopeDelTurno(Builder $consulta, int $idPersonal, int $idTurno, string $jornada): Builder
    {
        return $consulta->where('user_id', $idPersonal)
            ->where('schedule_id', $idTurno)
            ->where('shift_date', $jornada)
            ->where('status', self::PRESENTE);
    }

    /**
     * Marcaciones «presente» de un turno concreto en una jornada (día en que empieza el turno).
     * La asistencia de un turno no cuenta para otro: quien marcó en la noche no está presente en la mañana.
     */
    public function scopePresentesEnTurno(Builder $consulta, string $jornada, int $idTurno): Builder
    {
        return $consulta->where('status', self::PRESENTE)
            ->where('schedule_id', $idTurno)
            ->where('shift_date', $jornada);
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'user_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'schedule_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Reordena las marcaciones de un turno: la primera es la entrada (con su tardanza) y la segunda la salida.
     * Se llama después de registrar, editar o borrar para que el turno nunca quede con el tipo o la tardanza desactualizados.
     *
     * La tardanza se calcula con la hora de inicio que el turno tenía CUANDO se marcó (`shift_start`): si después se
     * edita el turno, las marcaciones antiguas no cambian. Primero se limpian los tipos y luego se asignan, para no
     * chocar con el índice único (usuario, turno, jornada, tipo) mientras se reordena.
     */
    public static function renumerarTurno(int $idPersonal, ?int $idTurno, ?string $jornada): void
    {
        $turno = $idTurno ? Turno::find($idTurno) : null;

        if (! $turno || ! $jornada) {
            return;
        }

        $marcaciones = static::delTurno($idPersonal, $turno->id, $jornada)
            ->orderBy('date')->orderBy('time')->orderBy('id')
            ->get();

        if ($marcaciones->isEmpty()) {
            return;
        }

        $inicio = $marcaciones->pluck('shift_start')->filter()->first() ?? substr((string) $turno->time_start, 0, 5);

        static::delTurno($idPersonal, $turno->id, $jornada)->update(['type' => null]);

        foreach ($marcaciones as $posicion => $marcacion) {
            $esEntrada = $posicion % 2 === 0;

            static::whereKey($marcacion->id)->update([
                'type' => $esEntrada ? self::ENTRADA : self::SALIDA,
                'late_minutes' => $esEntrada ? $turno->minutosDeTardanza($jornada, $marcacion->momento(), $inicio) : null,
                'shift_start' => $inicio,
            ]);
        }
    }
}
