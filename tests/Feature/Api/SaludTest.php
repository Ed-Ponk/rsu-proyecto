<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaludTest extends TestCase
{
    use RefreshDatabase;

    public function test_responde_con_el_contrato_existente_si_la_base_esta_lista(): void
    {
        $this->getJson('/api/v1/salud')
            ->assertOk()
            ->assertExactJson(['servicio' => 'rsu', 'version' => 3]);
    }

    public function test_reporta_indisponibilidad_sin_exponer_el_error_de_mysql(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.salud_sin_migrar' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        DB::setDefaultConnection('salud_sin_migrar');

        try {
            $this->getJson('/api/v1/salud')
                ->assertServiceUnavailable()
                ->assertExactJson(['servicio' => 'rsu', 'version' => 3]);
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('salud_sin_migrar');
        }
    }
}
