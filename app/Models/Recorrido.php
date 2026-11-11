<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Recorrido que hace el conductor para cumplir una programación (app móvil, Avance 6). */
class Recorrido extends Model
{
    use Auditable;

    public const EN_CURSO = 'en_curso';

    public const COMPLETADO = 'completado';

    public const ESTADOS = [self::EN_CURSO => 'En ruta', self::COMPLETADO => 'Completado'];

    protected $table = 'trips';

    protected $fillable = ['programming_id', 'driver_id', 'status', 'started_at', 'ended_at', 'observation'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(Programacion::class, 'programming_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'driver_id');
    }

    public function puntos(): HasMany
    {
        return $this->hasMany(PuntoRecorrido::class, 'trip_id')->orderBy('recorded_at')->orderBy('id');
    }

    public function enCurso(): bool
    {
        return $this->status === self::EN_CURSO;
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('status', self::EN_CURSO);
    }
}
