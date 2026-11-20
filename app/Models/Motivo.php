<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Motivo predefinido que se asigna a un cambio de programación (imprevistos, falla mecánica, personal faltante...). */
class Motivo extends Model
{
    use Auditable;

    protected $table = 'change_reasons';

    protected $fillable = ['name', 'description'];

    public function cambios(): HasMany
    {
        return $this->hasMany(Cambio::class, 'reason_id');
    }

    /** Texto que se propone como descripción del cambio al elegir el motivo. */
    public function getTextoAttribute(): string
    {
        return trim($this->name.($this->description ? ' - '.$this->description : ''));
    }
}
