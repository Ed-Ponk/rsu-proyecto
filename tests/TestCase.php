<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /** Versión que el cliente obtiene al abrir una ficha; no altera las peticiones de las pruebas nuevas. */
    protected function versionDe(string $url): array
    {
        $partes = explode('/', trim(parse_url($url, PHP_URL_PATH), '/'));
        $tablas = ['colores' => 'vehiclecolors', 'marcas' => 'brands', 'modelos' => 'brandmodels', 'tipos-vehiculo' => 'vehicletypes',
            'vehiculos' => 'vehicles', 'tipos-personal' => 'usertypes', 'personal' => 'users', 'contratos' => 'contracts',
            'asistencias' => 'attendances', 'vacaciones' => 'vacations', 'usuarios' => 'users', 'turnos' => 'schedules', 'zonas' => 'zones', 'feriados' => 'holidays'];
        $fila = DB::table($tablas[$partes[1]])->where('id', $partes[2])->first();
        $datos = ['version' => $fila->version ?? 1];
        if (($partes[3] ?? '') === 'estado') {
            $datos['is_active'] = ! $fila->is_active;
        }

        return $datos;
    }
}
