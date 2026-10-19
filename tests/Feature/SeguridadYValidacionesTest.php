<?php

namespace Tests\Feature;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\ColorVehiculo;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\User;
use Database\Seeders\TipoPersonalSeeder;
use Database\Seeders\TurnoSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm;
use Livewire\Livewire;
use Tests\TestCase;

/** Revisión general: acceso, contraseñas y validaciones de los módulos del Avance 01. */
class SeguridadYValidacionesTest extends TestCase
{
    use RefreshDatabase;

    private function persona(array $cambios = []): Personal
    {
        $this->seed([TipoPersonalSeeder::class, TurnoSeeder::class]);

        return Personal::create($cambios + [
            'dni' => '50000001', 'usertype_id' => TipoPersonal::ayudante()->id, 'name' => 'Pedro', 'last_name' => 'Soto',
            'birthdate' => '2000-06-15', 'email' => 'pedro@prueba.test', 'address' => 'Av. Principal 123, Chiclayo',
            'password' => 'secret123', 'status' => true,
        ]);
    }

    private function ajax(string $metodo, string $url, array $datos = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->{$metodo}($url, $datos);
    }

    public function test_el_ultimo_administrador_no_puede_eliminar_su_cuenta(): void
    {
        $this->actingAs($admin = User::factory()->create());

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertNotNull($admin->fresh());
    }

    public function test_el_personal_no_puede_iniciar_sesion_ni_recuperar_su_clave_por_correo(): void
    {
        $persona = $this->persona();
        $admin = User::factory()->create();

        // Inicio de sesión: solo cuentas sin tipo de personal.
        $this->post('/login', ['email' => $persona->email, 'password' => 'secret123'])->assertSessionHasErrors();
        $this->assertGuest();

        // Recuperación de contraseña: no se envía nada al personal, sí al administrador.
        Notification::fake();
        User::find($persona->id)->sendPasswordResetNotification('token');
        Notification::assertNothingSent();
        $admin->sendPasswordResetNotification('token');
        Notification::assertSentTo($admin, ResetPassword::class);

        // Y aunque alguien tuviera un enlace válido, la acción de restablecer lo rechaza.
        $this->expectException(ValidationException::class);
        (new ResetUserPassword)->reset(User::find($persona->id), ['password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123']);
    }

    public function test_la_contrasena_del_administrador_debe_ser_fuerte(): void
    {
        $regla = ['password' => ['required', Password::default()]];

        foreach (['corta1A', 'sinnumeros', 'sinmayusculas123', 'SINMINUSCULAS123', '12345678'] as $debil) {
            $this->assertTrue(Validator::make(['password' => $debil], $regla)->fails(), "{$debil} debería rechazarse");
        }

        $this->assertFalse(Validator::make(['password' => 'Admin12345'], $regla)->fails());
    }

    public function test_el_contrato_no_puede_empezar_antes_de_la_mayoria_de_edad_ni_en_fechas_absurdas(): void
    {
        $this->actingAs(User::factory()->create());
        $menor = $this->persona(['birthdate' => today()->subYears(19)->toDateString()]);

        // Con 17 años (hace 2 años) todavía no podía tener contrato.
        $this->ajax('post', route('admin.contratos.store'), [
            'user_id' => $menor->id, 'type' => 'permanente', 'start_date' => today()->subYears(2)->toDateString(), 'salary' => 1500, 'is_active' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('start_date');

        $this->ajax('post', route('admin.contratos.store'), [
            'user_id' => $menor->id, 'type' => 'permanente', 'start_date' => today()->addYears(2)->toDateString(), 'salary' => 1500, 'is_active' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('start_date');

        $this->ajax('post', route('admin.contratos.store'), [
            'user_id' => $menor->id, 'type' => 'temporal', 'start_date' => today()->toDateString(),
            'end_date' => today()->addYears(30)->toDateString(), 'salary' => 1500, 'is_active' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('end_date');

        $this->ajax('post', route('admin.contratos.store'), [
            'user_id' => $menor->id, 'type' => 'permanente', 'start_date' => today()->toDateString(), 'salary' => 1500, 'is_active' => 1,
        ])->assertOk();
    }

    public function test_el_codigo_de_un_color_no_se_repite(): void
    {
        $this->actingAs(User::factory()->create());
        ColorVehiculo::create(['name' => 'Azul', 'code' => '#104CAD']);

        $this->ajax('post', route('admin.colores.store'), ['name' => 'Azul marino', 'code' => '#104cad'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
        $this->ajax('post', route('admin.colores.store'), ['name' => 'Celeste', 'code' => '#87CEEB'])->assertOk();
    }

    public function test_el_logo_de_una_marca_no_admite_svg(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.marcas.store'), ['name' => 'Marca SVG', 'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('logo');
    }

    public function test_la_carga_por_lotes_de_feriados_respeta_las_fechas_permitidas(): void
    {
        $this->actingAs(User::factory()->create());

        $this->ajax('post', route('admin.feriados.cargar'), ['feriados' => [['date' => '2001-01-01', 'description' => 'Muy antiguo']]])
            ->assertStatus(422);
        $this->ajax('post', route('admin.feriados.cargar'), ['feriados' => [['date' => today()->year.'-05-01', 'description' => 'Día del Trabajo']]])
            ->assertOk()->assertJsonPath('creados', 1);
    }

    public function test_las_rutas_del_panel_exigen_iniciar_sesion(): void
    {
        foreach (['admin.inicio', 'admin.vacaciones.index', 'admin.zonas.index', 'admin.feriados.index', 'admin.turnos.index', 'admin.personal.index', 'admin.vehiculos.index'] as $ruta) {
            $this->get(route($ruta))->assertRedirect('/login');
        }

        $this->postJson(route('admin.zonas.verificar'), [])->assertUnauthorized();
        $this->getJson(route('admin.zonas.direccion', ['q' => 'plaza']))->assertUnauthorized();
    }

    public function test_el_personal_no_se_puede_contratar_si_esta_inactivo_ni_eliminar_con_contratos(): void
    {
        $this->actingAs(User::factory()->create());
        $persona = $this->persona(['status' => false]);

        $this->ajax('post', route('admin.contratos.store'), [
            'user_id' => $persona->id, 'type' => 'permanente', 'start_date' => today()->toDateString(), 'salary' => 1500, 'is_active' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');

        Contrato::create(['user_id' => $persona->id, 'type' => 'permanente', 'start_date' => today()->subYear()->toDateString(), 'salary' => 1500, 'is_active' => false]);
        $this->ajax('delete', route('admin.personal.destroy', $persona->id))->assertStatus(422);
    }
}
