<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clave interna fija para las funciones del sistema. Así "Conductor" y "Ayudante" pueden renombrarse
     * sin que se pierdan sus reglas (por ejemplo, la licencia obligatoria del conductor).
     */
    public function up(): void
    {
        Schema::table('usertypes', function (Blueprint $tabla) {
            $tabla->string('code', 30)->nullable()->unique()->after('id');
        });

        DB::table('usertypes')->where('name', 'Conductor')->update(['code' => 'conductor']);
        DB::table('usertypes')->where('name', 'Ayudante')->update(['code' => 'ayudante']);
    }

    public function down(): void
    {
        Schema::table('usertypes', function (Blueprint $tabla) {
            $tabla->dropUnique(['code']);
            $tabla->dropColumn('code');
        });
    }
};
