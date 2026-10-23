<?php

namespace App\Models;

use App\Models\Concerns\Versionado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ColorVehiculo extends Model
{
    use Versionado;

    protected $table = 'vehiclecolors';

    /** `code` guarda el color en RGB hexadecimal (#RRGGBB). */
    protected $fillable = ['name', 'code', 'description'];

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'color_id');
    }
}
