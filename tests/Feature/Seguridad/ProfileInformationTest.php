<?php

namespace Tests\Feature\Seguridad;

use App\Livewire\Perfil\ActualizarInformacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com', 'version' => $user->version])
            ->call('updateProfileInformation');

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_el_perfil_actualiza_su_version_entre_guardados(): void
    {
        $this->actingAs($user = User::factory()->create());
        $componente = Livewire::test(ActualizarInformacion::class)
            ->set('state.name', 'Primer nombre')->call('updateProfileInformation')->assertHasNoErrors();
        $this->assertSame(2, (int) $componente->state['version']);
        $componente->set('state.name', 'Segundo nombre')->call('updateProfileInformation')->assertHasNoErrors();
        $this->assertSame('Segundo nombre', $user->fresh()->name);
    }

    public function test_recuperacion_por_consola_reactiva_y_aplica_la_clave(): void
    {
        $usuario = User::factory()->create(['status' => false]);
        $this->artisan('admin:cambiar-clave', ['correo' => $usuario->email])
            ->expectsQuestion('Nueva contraseña (mínimo 8 caracteres con mayúsculas, minúsculas y números)', 'ClaveNueva123')
            ->assertSuccessful();
        $this->assertTrue((bool) $usuario->fresh()->status);
        $this->assertTrue(Hash::check('ClaveNueva123', $usuario->fresh()->password));
    }
}
