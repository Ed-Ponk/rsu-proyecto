<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Un día de trabajo programado: quién va, en qué vehículo, a qué zona y en qué turno. */
class Programacion extends Model
{
    use Auditable;

    public const PROGRAMADO = 'programado';

    public const REPROGRAMADO = 'reprogramado';

    public const FINALIZADO = 'finalizado';

    /** Estados en los que todavía se puede modificar, eliminar o finalizar (aún no se cumplió). */
    public const PENDIENTES = [self::PROGRAMADO, self::REPROGRAMADO];

    public const ESTADOS = [self::PROGRAMADO => 'Programado', self::REPROGRAMADO => 'Reprogramado', self::FINALIZADO => 'Finalizado'];

    protected $table = 'programmings';

    protected $fillable = ['date', 'group_id', 'zone_id', 'shift_id', 'vehicle_id', 'driver_id', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoPersonal::class, 'group_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zone_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'shift_id');
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehicle_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'driver_id');
    }

    public function ayudantes(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'programming_helpers', 'programming_id', 'user_id')
            ->withPivot('position')->orderByPivot('position');
    }

    /** ¿Sigue pendiente (programada o reprogramada)? Una finalizada ya no se toca. */
    public function esProgramado(): bool
    {
        return in_array($this->status, self::PENDIENTES, true);
    }

    public function scopePendientes(Builder $consulta): Builder
    {
        return $consulta->whereIn('status', self::PENDIENTES);
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(Cambio::class, 'programming_id');
    }

    /**
     * Personas de la programación (conductor y ayudantes) que ya registraron asistencia en su turno ese día.
     * La asistencia de otro turno del mismo día no cuenta.
     *
     * @return Collection<int, Personal>
     */
    public function personasConAsistencia()
    {
        $ids = Asistencia::presentesEnTurno($this->date->toDateString(), (int) $this->shift_id)
            ->whereIn('user_id', [$this->driver_id, ...$this->ayudantes()->pluck('users.id')->all()])
            ->pluck('user_id');

        return Personal::whereIn('users.id', $ids)->get();
    }

    /** Hora (en minutos desde la medianoche) a la que empieza el turno de la programación. */
    public function minutoDeInicio(): int
    {
        $inicio = $this->turno?->time_start ?? '00:00';

        return ((int) substr($inicio, 0, 2)) * 60 + (int) substr($inicio, 3, 2);
    }

    /** Programaciones que caen en un día feriado activo. */
    public function scopeEnFeriado(Builder $consulta): Builder
    {
        return $consulta->whereIn('date', Feriado::activo()->select('date'));
    }

    /** Programaciones en las que la persona va como conductor o como ayudante. */
    public function scopeDePersona(Builder $consulta, int $idPersona): Builder
    {
        return $consulta->where(fn (Builder $q) => $q->where('driver_id', $idPersona)
            ->orWhereHas('ayudantes', fn (Builder $a) => $a->where('users.id', $idPersona)));
    }

    public function scopeEntre(Builder $consulta, string $desde, string $hasta): Builder
    {
        return $consulta->where('date', '>=', $desde)->where('date', '<=', $hasta);
    }

    /** Cambia el turno de la programación y el que llevan copiado sus ayudantes (la clave única es persona + fecha + turno). */
    public function moverATurno(int $turnoId): void
    {
        $this->update(['shift_id' => $turnoId]);
        DB::table('programming_helpers')->where('programming_id', $this->id)->update(['shift_id' => $turnoId]);
    }

    /** Cambia los ayudantes (con su fecha y turno copiados, para el único por persona). */
    public function asignarAyudantes(array $ids): void
    {
        $this->ayudantes()->detach();

        foreach (array_values($ids) as $posicion => $id) {
            $this->ayudantes()->attach($id, [
                'date' => $this->date->toDateString(),
                'shift_id' => $this->shift_id,
                'position' => $posicion + 1,
            ]);
        }
    }
}
