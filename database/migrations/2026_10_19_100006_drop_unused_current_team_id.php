<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Los equipos de Jetstream no se usan: se quita la columna que dejó la plantilla. */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'current_team_id')) {
            Schema::table('users', fn (Blueprint $tabla) => $tabla->dropColumn('current_team_id'));
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $tabla) => $tabla->foreignId('current_team_id')->nullable());
    }
};
