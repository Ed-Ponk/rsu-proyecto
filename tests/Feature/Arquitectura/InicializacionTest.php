<?php

namespace Tests\Feature\Arquitectura;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InicializacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_repetir_la_inicializacion_conserva_las_credenciales_del_administrador(): void
    {
        config(['proyecto.admin.correo' => 'inicial@prueba.test', 'proyecto.admin.clave' => 'Inicial12345']);
        $usuario = User::factory()->create([
            'email' => 'inicial@prueba.test', 'name' => 'Nombre personalizado', 'password' => 'Personal12345',
        ]);
        $hashOriginal = $usuario->password;

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($hashOriginal, $usuario->fresh()->password);
        $this->assertSame('Nombre personalizado', $usuario->fresh()->name);
        $this->assertTrue(Hash::check('Personal12345', $usuario->fresh()->password));
    }
}
