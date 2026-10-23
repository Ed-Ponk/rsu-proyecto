<?php

namespace App\Providers;

use App\Models\User;
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
        // Se comprueba en cada solicitud: desactivar una cuenta también corta sus sesiones abiertas.
        Gate::define('administrar-panel', fn (User $usuario) => $usuario->usertype_id === null && (bool) $usuario->status);

        // Contraseñas de las cuentas del panel: mínimo 8 caracteres con mayúsculas, minúsculas y números.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());
    }
}
