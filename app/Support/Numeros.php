<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Quien escribe cantidades en Perú usa coma decimal ("1,5") y punto para los miles ("1.500,50"): se entiende en vez de rechazarlo. */
class Numeros
{
    public static function normalizar(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }

        $texto = trim($valor);

        if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $texto)) {   // 1.500,50
            return str_replace(['.', ','], ['', '.'], $texto);
        }

        if (preg_match('/^\d+,\d+$/', $texto)) {   // 1,5
            return str_replace(',', '.', $texto);
        }

        return $valor;
    }

    /** @param list<string> $campos */
    public static function enCampos(Request $request, array $campos): void
    {
        foreach ($campos as $campo) {
            if ($request->has($campo)) {
                $request->merge([$campo => self::normalizar($request->input($campo))]);
            }
        }
    }
}
