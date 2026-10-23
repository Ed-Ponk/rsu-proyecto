<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Siempre dentro de una transacción. Orden: conjunto de turnos, persona, registro. */
class Bloqueo
{
    public static function tomar(string $recurso): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('El bloqueo necesita una transacción.');
        }

        if (! DB::table('bloqueos')->where('recurso', $recurso)->lockForUpdate()->first()) {
            throw new \LogicException('Falta la fila de bloqueo: '.$recurso);
        }
    }
}
