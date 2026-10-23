<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Versionado;
use App\Services\ReglasZona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Zona de recolección. Su perímetro es el polígono que forman sus coordenadas, en el orden de `position`. */
class Zona extends Model
{
    use Auditable;
    use Versionado;

    protected $table = 'zones';

    protected $fillable = ['name', 'department', 'province', 'district', 'description', 'average_waste', 'status'];

    protected function casts(): array
    {
        return [
            'average_waste' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function coordenadas(): HasMany
    {
        return $this->hasMany(ZonaCoordenada::class, 'zone_id')->orderBy('position');
    }

    /** Vértices como pares [latitud, longitud] (el formato que usa Leaflet). */
    public function vertices(): array
    {
        return $this->coordenadas->map(fn (ZonaCoordenada $c) => [(float) $c->latitude, (float) $c->longitude])->all();
    }

    /** Área aproximada del polígono en km² (fórmula del "lazo" sobre una proyección plana local). */
    public function areaKm2(): float
    {
        return ReglasZona::areaKm2($this->vertices());
    }
}
