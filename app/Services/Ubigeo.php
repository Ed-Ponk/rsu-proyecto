<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Departamentos, provincias y distritos. Se leen de la base de datos (se pueden ampliar sin tocar código);
 * si las tablas todavía están vacías se usa config/ubigeo.php, que es también la fuente del UbigeoSeeder.
 */
class Ubigeo
{
    /** @return array<string, array<string, array<string, array{0: float, 1: float}>>> departamento => provincia => distrito => [lat, lng] */
    public function catalogo(): array
    {
        $filas = DB::table('districts as d')
            ->join('provinces as p', 'p.id', '=', 'd.province_id')
            ->join('departments as x', 'x.id', '=', 'p.department_id')
            ->orderBy('x.name')->orderBy('p.name')->orderBy('d.name')
            ->get(['x.name as departamento', 'p.name as provincia', 'd.name as distrito', 'd.latitude', 'd.longitude']);

        if ($filas->isEmpty()) {
            return config('ubigeo.departamentos');
        }

        $catalogo = [];
        foreach ($filas as $fila) {
            $catalogo[$fila->departamento][$fila->provincia][$fila->distrito] = [(float) $fila->latitude, (float) $fila->longitude];
        }

        return $catalogo;
    }

    public function existe(string $departamento, string $provincia, string $distrito): bool
    {
        return isset($this->catalogo()[$departamento][$provincia][$distrito]);
    }

    public function departamentoExiste(string $departamento): bool
    {
        return isset($this->catalogo()[$departamento]);
    }

    public function provinciaExiste(string $departamento, string $provincia): bool
    {
        return isset($this->catalogo()[$departamento][$provincia]);
    }
}
