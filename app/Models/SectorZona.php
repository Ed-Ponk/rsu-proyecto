<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sector (barrio, urbanización) que cubre una zona. */
class SectorZona extends Model
{
    protected $table = 'zone_sectors';

    protected $fillable = ['zone_id', 'name', 'position'];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zone_id');
    }
}
