<?php

namespace App\Models\Concerns;

use App\Models\Bitacora;

/** Deja constancia en la bitácora de quién creó, modificó o eliminó cada registro del modelo. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($modelo) => Bitacora::registrar('creado', $modelo, null, $modelo->getAttributes()));

        static::updated(function ($modelo) {
            $cambios = $modelo->getChanges();
            unset($cambios['updated_at'], $cambios['version']);

            Bitacora::registrar('modificado', $modelo, array_intersect_key($modelo->getOriginal(), $cambios), $cambios);
        });

        static::deleted(fn ($modelo) => Bitacora::registrar('eliminado', $modelo, $modelo->getAttributes(), null));
    }
}
