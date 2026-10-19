<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una persona solo puede tener UN contrato activo. La columna generada vale el id de la persona cuando el contrato
     * está activo y NULL cuando no, así el índice único solo aplica a los activos (los NULL no se comparan).
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('active_user_id')->nullable()->virtualAs('CASE WHEN is_active = 1 THEN user_id ELSE NULL END');
            $tabla->unique('active_user_id', 'contracts_un_solo_activo');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $tabla) {
            $tabla->dropUnique('contracts_un_solo_activo');
            $tabla->dropColumn('active_user_id');
        });
    }
};
