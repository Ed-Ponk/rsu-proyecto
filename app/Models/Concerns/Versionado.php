<?php

namespace App\Models\Concerns;

trait Versionado
{
    public function initializeVersionado(): void
    {
        $this->attributes['version'] = 1;
    }

    public static function bootVersionado(): void
    {
        static::updating(function ($registro) {
            $registro->version = (int) $registro->getOriginal('version') + 1;
        });
    }
}
