<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Turno de trabajo (Mañana, Tarde, Noche). Si la hora de fin es menor o igual a la de inicio, cruza la medianoche. */
class Turno extends Model
{
    protected $table = 'schedules';

    protected $fillable = ['name', 'time_start', 'time_end', 'description'];

    public function getRangoAttribute(): string
    {
        return substr($this->time_start, 0, 5).' - '.substr($this->time_end, 0, 5);
    }

    public function getEtiquetaAttribute(): string
    {
        return "{$this->name} ({$this->rango})";
    }
}
