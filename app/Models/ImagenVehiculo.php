<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagenVehiculo extends Model
{
    protected $table = 'vehicleimages';

    protected $fillable = ['image', 'profile', 'vehicle_id'];

    protected function casts(): array
    {
        return ['profile' => 'boolean'];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehicle_id');
    }

    public function getUrlAttribute(): string
    {
        $ruta = (string) $this->image;

        return preg_match('#^(https?://|/)#', $ruta) ? $ruta : asset($ruta);
    }
}
