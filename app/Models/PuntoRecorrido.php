<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Posición GPS registrada por la app del conductor durante un recorrido. Sin bitácora: son miles de filas. */
class PuntoRecorrido extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'trip_points';

    protected $fillable = ['trip_id', 'latitude', 'longitude', 'accuracy', 'recorded_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'accuracy' => 'float', 'recorded_at' => 'datetime'];
    }

    public function recorrido(): BelongsTo
    {
        return $this->belongsTo(Recorrido::class, 'trip_id');
    }
}
