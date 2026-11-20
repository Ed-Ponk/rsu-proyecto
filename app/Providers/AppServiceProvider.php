<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CatalogoPermisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Contraseñas de las cuentas del panel: mínimo 8 caracteres con mayúsculas, minúsculas y números.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        // Texto de un campo del formulario: si llega un arreglo u otro tipo (petición manipulada) se toma como vacío y la validación
        // responde 422, en vez de romper con «Array to string conversion» (500).
        Request::macro('texto', function (string $campo): string {
            /** @var Request $this */
            $valor = $this->input($campo);

            return is_scalar($valor) ? (string) $valor : '';
        });

        // Cada permiso del catálogo es una habilidad de Gate (@can('colors.view'), 'can' en el menú, $user->can(...)).
        foreach (CatalogoPermisos::codigos() as $codigo) {
            Gate::define($codigo, fn (User $usuario) => $usuario->tienePermiso($codigo));
        }
    }
}
