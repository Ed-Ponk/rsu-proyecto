<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Versionado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Día feriado. Solo los activos cuentan para la programación de rutas. */
class Feriado extends Model
{
    use Auditable;
    use Versionado;

    protected $table = 'holidays';

    protected $fillable = ['date', 'description', 'status'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',   // solo el día: así la fecha única se compara igual en MySQL y SQLite
            'status' => 'boolean',
        ];
    }

    public function scopeActivo(Builder $consulta): Builder
    {
        return $consulta->where('status', true);
    }

    /** ¿La fecha es un feriado activo? */
    public static function esFeriado(Carbon|string $fecha): bool
    {
        return static::activo()->where('date', Carbon::parse($fecha)->toDateString())->exists();
    }

    /** Nombre del día de la semana en español (lunes, martes...). */
    public function getDiaSemanaAttribute(): string
    {
        return $this->date->locale('es')->isoFormat('dddd');
    }
}
