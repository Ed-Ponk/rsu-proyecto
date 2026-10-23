<?php

namespace App\Models;

use App\Models\Concerns\Versionado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modelo de vehículo (Atego, Dyna...). Cada modelo pertenece a una marca. */
class Modelo extends Model
{
    use Versionado;

    protected $table = 'brandmodels';

    protected $fillable = ['name', 'code', 'description', 'brand_id'];

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'brand_id');
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'model_id');
    }
}
