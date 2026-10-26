<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Plantilla de programación: turno, zona, vehículo, días de la semana, conductor y ayudantes ya definidos. */
class GrupoPersonal extends Model
{
    use Auditable;

    protected $table = 'staffgroups';

    /** Días de la semana (ISO: 1 = lunes ... 7 = domingo). */
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected $fillable = ['name', 'shift_id', 'zone_id', 'vehicle_id', 'weekdays', 'driver_id', 'status'];

    protected function casts(): array
    {
        return ['weekdays' => 'array', 'status' => 'boolean'];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'shift_id');
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zone_id');
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
        return $this->belongsToMany(Personal::class, 'staffgroup_helpers', 'group_id', 'user_id')
            ->withPivot('position')->orderByPivot('position');
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(Programacion::class, 'group_id');
    }

    /** Días ordenados y como números enteros. */
    public function getDiasOrdenadosAttribute(): array
    {
        $dias = array_map('intval', $this->weekdays ?? []);
        sort($dias);

        return array_values(array_unique($dias));
    }
}
