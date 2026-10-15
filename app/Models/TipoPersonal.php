<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Función del personal operativo. "Conductor" y "Ayudante" vienen del sistema y no se pueden borrar ni renombrar. */
class TipoPersonal extends Model
{
    protected $table = 'usertypes';

    public const CONDUCTOR = 'Conductor';

    public const AYUDANTE = 'Ayudante';

    protected $fillable = ['name', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function personal(): HasMany
    {
        return $this->hasMany(Personal::class, 'usertype_id');
    }

    public static function conductor(): ?self
    {
        return static::firstWhere('name', self::CONDUCTOR);
    }

    public static function ayudante(): ?self
    {
        return static::firstWhere('name', self::AYUDANTE);
    }
}
