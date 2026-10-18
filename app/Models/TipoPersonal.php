<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Función del personal operativo. "Conductor" y "Ayudante" vienen del sistema: se reconocen por su clave
 * interna (`code`), no por el nombre, así que pueden renombrarse sin perder sus reglas.
 */
class TipoPersonal extends Model
{
    protected $table = 'usertypes';

    /** Nombres con los que se crean las funciones del sistema. */
    public const CONDUCTOR = 'Conductor';

    public const AYUDANTE = 'Ayudante';

    /** Claves internas: no se muestran ni se editan. */
    public const CODIGO_CONDUCTOR = 'conductor';

    public const CODIGO_AYUDANTE = 'ayudante';

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
        return static::firstWhere('code', self::CODIGO_CONDUCTOR);
    }

    public static function ayudante(): ?self
    {
        return static::firstWhere('code', self::CODIGO_AYUDANTE);
    }
}
