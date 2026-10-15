<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Contrato extends Model
{
    protected $table = 'contracts';

    public const NOMBRADO = 'nombrado';

    public const PERMANENTE = 'permanente';

    public const TEMPORAL = 'temporal';

    /** Valor guardado => texto que ve el usuario. */
    public const TIPOS = [
        self::NOMBRADO => 'Nombrado',
        self::PERMANENTE => 'Permanente',
        self::TEMPORAL => 'Temporal',
    ];

    protected $fillable = [
        'user_id', 'type', 'start_date', 'end_date', 'salary', 'probation_months', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'salary' => 'decimal:2',
            'probation_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'user_id');
    }

    public function esTemporal(): bool
    {
        return $this->type === self::TEMPORAL;
    }

    /** Contratos activos que ya empezaron y todavía no vencen en la fecha indicada. */
    public function scopeVigente(Builder $consulta, ?Carbon $fecha = null): Builder
    {
        $dia = ($fecha ?? today())->toDateString();

        return $consulta->where('is_active', true)
            ->whereDate('start_date', '<=', $dia)
            ->where(function (Builder $c) use ($dia) {
                $c->whereNull('end_date')->orWhereDate('end_date', '>=', $dia);
            });
    }

    /** Desactiva los temporales cuya fecha de término ya pasó y devuelve cuántos cerró. */
    public static function cerrarVencidos(): int
    {
        return static::where('is_active', true)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', today()->toDateString())
            ->update(['is_active' => false]);
    }

    public function getEstadoAttribute(): string
    {
        if (! $this->is_active) {
            return $this->end_date?->lt(today()) ? 'Vencido' : 'Inactivo';
        }

        return $this->start_date->gt(today()) ? 'Por iniciar' : 'Vigente';
    }
}
