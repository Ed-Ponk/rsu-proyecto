<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Vacacion extends Model
{
    protected $table = 'vacations';

    public const PENDIENTE = 'pendiente';

    public const APROBADA = 'aprobada';

    public const RECHAZADA = 'rechazada';

    public const ESTADOS = [
        self::PENDIENTE => 'Pendiente',
        self::APROBADA => 'Aprobada',
        self::RECHAZADA => 'Rechazada',
    ];

    protected $fillable = ['user_id', 'start_date', 'end_date', 'days', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'user_id');
    }

    public function esPendiente(): bool
    {
        return $this->status === self::PENDIENTE;
    }

    /** Solicitudes que ocupan sus fechas: las pendientes y las aprobadas (las rechazadas quedan libres). */
    public function scopeOcupaFechas(Builder $consulta): Builder
    {
        return $consulta->whereIn('status', [self::PENDIENTE, self::APROBADA]);
    }

    /** Solicitudes cuyo rango toca el rango indicado (los extremos cuentan). */
    public function scopeQueSeCruzaCon(Builder $consulta, Carbon $inicio, Carbon $fin): Builder
    {
        return $consulta->whereDate('start_date', '<=', $fin->toDateString())
            ->whereDate('end_date', '>=', $inicio->toDateString());
    }

    /** Fecha de término a partir del inicio y los días pedidos (corridos: el día de inicio ya cuenta). */
    public static function fechaFin(Carbon $inicio, int $dias): Carbon
    {
        return $inicio->copy()->addDays(max($dias, 1) - 1);
    }

    /** Días de vacaciones que la persona ya tiene aprobados en el año. */
    public static function diasAprobados(int $idPersonal, int $anio, ?int $ignorarId = null): int
    {
        return (int) static::where('user_id', $idPersonal)
            ->where('status', self::APROBADA)
            ->whereYear('start_date', $anio)
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->sum('days');
    }

    /** Días que le quedan a la persona en el año (el cupo anual sale de config/proyecto.php). */
    public static function diasDisponibles(int $idPersonal, int $anio): int
    {
        return max(0, (int) config('proyecto.vacaciones.dias_por_anio') - static::diasAprobados($idPersonal, $anio));
    }

    /** ¿La persona está de vacaciones aprobadas en esa fecha? */
    public static function estaDeVacaciones(int $idPersonal, Carbon|string $fecha): bool
    {
        $dia = Carbon::parse($fecha);

        return static::where('user_id', $idPersonal)
            ->where('status', self::APROBADA)
            ->queSeCruzaCon($dia, $dia)
            ->exists();
    }
}
