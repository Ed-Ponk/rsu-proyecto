<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Administrador inicial (sin tipo de personal, por eso puede iniciar sesión). Cambie la clave al ingresar.
        User::updateOrCreate(
            ['email' => 'admin@rsu.test'],
            ['name' => 'Administrador', 'password' => 'Admin12345', 'email_verified_at' => now()],
        );

        // El orden importa: cada seeder necesita los datos del anterior.
        $this->call([
            TipoPersonalSeeder::class,
            TurnoSeeder::class,
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
}
