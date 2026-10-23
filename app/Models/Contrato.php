<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Versionado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Contrato extends Model
{
    use Auditable;
    use Versionado;

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
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
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
            ->where('start_date', '<=', $dia)
            ->where(function (Builder $c) use ($dia) {
                $c->whereNull('end_date')->orWhere('end_date', '>=', $dia);
            });
    }

    /** Contratos activos que todavía no vencieron (sin importar si ya empezaron): el que "ocupa" a la persona. */
    public function scopeNoVencido(Builder $consulta): Builder
    {
        return $consulta->where('is_active', true)
            ->where(fn (Builder $c) => $c->whereNull('end_date')->orWhere('end_date', '>=', today()->toDateString()));
    }

    /** Desactiva los temporales cuya fecha de término ya pasó y devuelve cuántos cerró. */
    public static function cerrarVencidos(): int
    {
        $cerrados = 0;
        $personas = static::where('is_active', true)->whereNotNull('end_date')->where('end_date', '<', today()->toDateString())
            ->distinct()->orderBy('user_id')->pluck('user_id');
        foreach ($personas as $idPersonal) {
            $cerrados += DB::transaction(function () use ($idPersonal) {
                Personal::whereKey($idPersonal)->lockForUpdate()->firstOrFail();
                $contratos = static::where('user_id', $idPersonal)->where('is_active', true)
                    ->whereNotNull('end_date')->where('end_date', '<', today()->toDateString())->get();
                foreach ($contratos as $contrato) {
                    $contrato->update(['is_active' => false]);
                }

                return $contratos->count();
            });
        }

        return $cerrados;
    }

    public function getEstadoAttribute(): string
    {
        if (! $this->is_active) {
            return $this->end_date?->lt(today()) ? 'Vencido' : 'Inactivo';
        }

        return $this->start_date->gt(today()) ? 'Por iniciar' : 'Vigente';
    }
}
