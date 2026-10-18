<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Respuesta común de los buscadores de catálogos (tipos, colores, modelos...): filtra por las palabras escritas
 * sin distinguir tildes ni mayúsculas y devuelve los primeros resultados con la forma que espera Tads.buscador.
 */
trait BuscaEnCatalogo
{
    /**
     * @param  Collection  $registros  catálogo ya ordenado
     * @param  callable  $textoDe  registro => texto donde se busca
     * @param  callable  $itemDe  registro => ['id', 'titulo', 'detalle', ...] para el buscador
     */
    protected function respuestaDeBusqueda(Request $request, Collection $registros, callable $textoDe, callable $itemDe, int $limite = 8): JsonResponse
    {
        $datos = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        $normalizar = fn (?string $texto) => Str::lower(Str::ascii((string) $texto));
        $palabras = array_filter(preg_split('/\s+/', $normalizar(trim((string) ($datos['q'] ?? '')))));

        $encontrados = $registros
            ->filter(function ($registro) use ($palabras, $textoDe, $normalizar) {
                $texto = $normalizar($textoDe($registro));

                foreach ($palabras as $palabra) {
                    if (! str_contains($texto, $palabra)) {
                        return false;
                    }
                }

                return true;
            })
            ->take($limite)
            ->values();

        return response()->json(['data' => $encontrados->map($itemDe)]);
    }

    protected function plural(int $cantidad, string $singular, string $plural): string
    {
        return $cantidad === 1 ? "1 {$singular}" : "{$cantidad} {$plural}";
    }
}
