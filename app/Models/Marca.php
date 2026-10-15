<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    protected $table = 'brands';

    protected $fillable = ['name', 'description', 'logo'];

    public function modelos(): HasMany
    {
        return $this->hasMany(Modelo::class, 'brand_id');
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'brand_id');
    }
}
