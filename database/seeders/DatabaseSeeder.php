<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->crearAdministrador();

        // Catálogos que el sistema necesita para funcionar en cualquier entorno.
        $this->call([
            TipoPersonalSeeder::class,
            TurnoSeeder::class,
            UbigeoSeeder::class,
        ]);

        // Datos de ejemplo (con claves públicas como "secret123"): solo en desarrollo y pruebas.
        if (! $this->esEntornoDeDesarrollo()) {
            $this->command?->warn('Entorno de producción: se omitieron los datos de ejemplo.');

            return;
        }

        // El orden importa: cada seeder necesita los datos del anterior.
        $this->call([
            FeriadoSeeder::class,
            ZonaSeeder::class,
            MarcaSeeder::class,
            ModeloSeeder::class,
            TipoVehiculoSeeder::class,
            ColorVehiculoSeeder::class,
            VehiculoSeeder::class,
            ImagenVehiculoSeeder::class,
            PersonalSeeder::class,
            VacacionSeeder::class,
        ]);
    }

    private function esEntornoDeDesarrollo(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    /**
     * En desarrollo: admin@rsu.test / Admin12345 (documentado en el README).
     * En producción: el correo y la clave salen de ADMIN_EMAIL y ADMIN_PASSWORD, y la clave debe ser fuerte.
     */
    private function crearAdministrador(): void
    {
        if ($this->esEntornoDeDesarrollo()) {
            $correo = config('proyecto.admin.correo') ?: 'admin@rsu.test';
            $clave = config('proyecto.admin.clave') ?: 'Admin12345';
        } else {
            $correo = config('proyecto.admin.correo');
            $clave = config('proyecto.admin.clave');

            if (blank($correo) || blank($clave)) {
                throw new RuntimeException('En producción defina ADMIN_EMAIL y ADMIN_PASSWORD en el .env antes de ejecutar el seeder.');
            }

            if (Validator::make(['password' => $clave], ['password' => [Password::min(10)->mixedCase()->numbers()]])->fails()) {
                throw new RuntimeException('ADMIN_PASSWORD es débil: use al menos 10 caracteres con mayúsculas, minúsculas y números.');
            }
        }

        // Administrador (sin tipo de personal, por eso puede iniciar sesión). Cambie la clave al ingresar.
        User::updateOrCreate(
            ['email' => $correo],
            ['name' => 'Administrador', 'password' => $clave, 'email_verified_at' => now()],
        );
    }
}
