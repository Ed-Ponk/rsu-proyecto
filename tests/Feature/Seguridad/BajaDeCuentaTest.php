<?php

namespace Tests\Feature\Seguridad;

use App\Actions\Jetstream\DeleteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BajaDeCuentaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $base = DB::connection()->getDatabaseName();
        $this->assertTrue($base === ':memory:' || str_ends_with($base, '_pruebas'));
        // Necesita un commit real para comprobar afterCommit, sin rollback de migraciones históricas.
        Artisan::call('migrate:fresh');
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_limpiar_la_foto_despues_del_commit_no_recrea_la_cuenta(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-photos/revision.jpg', 'contenido de prueba');
        $usuario = User::factory()->create(['profile_photo_path' => 'profile-photos/revision.jpg']);
        User::factory()->create();
        $this->actingAs($usuario);

        app(DeleteUser::class)->delete($usuario);

        $this->assertDatabaseMissing('users', ['id' => $usuario->id]);
        Storage::disk('public')->assertMissing('profile-photos/revision.jpg');
    }
}
