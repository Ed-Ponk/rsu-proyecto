<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehiculo extends Model
{
    protected $table = 'vehicles';

    /** Placas válidas: XXXXXX, XX-XXXX o XXX-XXX (letras y números). */
    public const FORMATO_PLACA = '/^([A-Z0-9]{6}|[A-Z0-9]{2}-[A-Z0-9]{4}|[A-Z0-9]{3}-[A-Z0-9]{3})$/';

    protected $fillable = [
        'name', 'code', 'plate', 'year', 'load_capacity', 'fuel_capacity', 'compact_capacity',
        'occupant_capacity', 'description', 'status', 'brand_id', 'model_id', 'type_id', 'color_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'load_capacity' => 'float',
            'fuel_capacity' => 'float',
            'compact_capacity' => 'float',
        ];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'brand_id');
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(Modelo::class, 'model_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class, 'type_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(ColorVehiculo::class, 'color_id');
    }

    /** La imagen de perfil siempre va primera. */
    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenVehiculo::class, 'vehicle_id')->orderByDesc('profile')->orderBy('id');
    }
}
