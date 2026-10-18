<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vértice del perímetro de una zona. */
class ZonaCoordenada extends Model
{
    protected $table = 'zonecoords';

    protected $fillable = ['zone_id', 'latitude', 'longitude', 'position'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'position' => 'integer',
        ];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zone_id');
    }
}
