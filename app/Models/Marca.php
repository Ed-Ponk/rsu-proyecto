<?php

namespace App\Models;

use App\Models\Concerns\Versionado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    use Versionado;

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

    /** URL pública del logo, o null si no tiene (o si quedó guardada una ruta local de Windows). */
    public function getUrlLogoAttribute(): ?string
    {
        $ruta = $this->logo;

        if (blank($ruta) || preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta)) {
            return null;
        }

        return preg_match('#^(https?://|/)#', $ruta) ? $ruta : asset($ruta);
    }
}
