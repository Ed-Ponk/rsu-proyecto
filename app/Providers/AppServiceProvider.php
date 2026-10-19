<?php

namespace App\Providers;

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
    }
}
