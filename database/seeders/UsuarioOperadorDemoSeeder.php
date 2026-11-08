<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo del Avance 05 (solo desarrollo): un operador que solo puede consultar los catálogos de vehículos
 * y la programación. Entra con operador@rsu.test / Operador123 y sirve para probar el menú y las rutas con permisos.
 */
class UsuarioOperadorDemoSeeder extends Seeder
{
    public function run(): void
    {
        $operador = User::updateOrCreate(['email' => 'operador@rsu.test'], [
            'name' => 'Miguel', 'password' => 'Operador123', 'email_verified_at' => now(),
        ]);

        $operador->forceFill([
            'last_name' => 'Operador Demo', 'dni' => '42311349', 'birthdate' => '1990-06-15', 'phone' => '987654321',
            'address' => 'Av. Principal 123, José Leonardo Ortiz', 'role' => User::OPERADOR, 'status' => true,
        ])->save();

        foreach (['colors.view', 'brands.view', 'brandmodels.view', 'vehicletypes.view', 'vehicles.view', 'scheduling.view', 'dashboard.view'] as $codigo) {
            $operador->permisos()->firstOrCreate(['permission' => $codigo]);
        }
    }
}
