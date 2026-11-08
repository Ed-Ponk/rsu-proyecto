<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avance 05: gestión de usuarios con permisos.
     * - `users.role`: tipo de usuario del sistema (administrador = acceso total; operador = solo lo que se le conceda).
     *   Por defecto «administrador»: las cuentas que ya existían conservan todo su acceso. No aplica al personal (usertype_id).
     * - `user_permissions`: un permiso concedido por fila, con el código `recurso.accion` (ej. colors.view).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->string('role', 20)->default('administrador')->after('usertype_id');
        });

        Schema::create('user_permissions', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $tabla->string('permission', 60);
            $tabla->timestamps();

            $tabla->unique(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');

        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropColumn('role');
        });
    }
};
