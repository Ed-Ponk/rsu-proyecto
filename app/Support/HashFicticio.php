<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Cuando el usuario no existe se compara igual contra un hash de mentira: así la respuesta tarda lo mismo
 * exista o no el DNI/correo y no se puede averiguar cuáles están registrados midiendo el tiempo.
 */
class HashFicticio
{
    private const HASH = '$2y$12$euBkvuC1eRT5esyvN/LiyuS8/p9ffd3zMeaNKKygzfF/809qzZ8GW';

    public static function verificar(string $clave): bool
    {
        Hash::check($clave, self::HASH);

        return false;
    }
}
