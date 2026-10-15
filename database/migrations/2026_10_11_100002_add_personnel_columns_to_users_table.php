<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `users` guarda a los usuarios del sistema (usertype_id NULL) y al personal operativo
     * (conductores y ayudantes: usertype_id con valor), como plantea el diagrama del docente.
     * `name` = nombres; `profile_photo_path` = foto del personal.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->string('last_name', 100)->nullable()->after('name');
            $tabla->string('dni', 8)->nullable()->unique()->after('last_name');
            $tabla->date('birthdate')->nullable()->after('dni');
            $tabla->string('phone', 9)->nullable()->after('birthdate');
            $tabla->string('license', 9)->nullable()->unique()->after('phone');   // solo conductores
            $tabla->string('address')->nullable()->after('license');
            $tabla->boolean('status')->default(true)->after('address');
            $tabla->foreignId('usertype_id')->nullable()->after('status')->constrained('usertypes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('usertype_id');
            $tabla->dropColumn(['last_name', 'dni', 'birthdate', 'phone', 'license', 'address', 'status']);
        });
    }
};
