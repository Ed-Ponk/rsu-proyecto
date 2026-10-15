<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoVehiculo extends Model
{
    protected $table = 'vehicletypes';

    protected $fillable = ['name', 'description'];

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'type_id');
    }
}
