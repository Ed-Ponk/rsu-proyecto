<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** El código del modelo deja de ser único global: solo no puede repetirse dentro de la misma marca. */
    public function up(): void
    {
        Schema::table('brandmodels', function (Blueprint $tabla) {
            $tabla->dropUnique(['code']);
            $tabla->unique(['brand_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('brandmodels', function (Blueprint $tabla) {
            $tabla->dropUnique(['brand_id', 'code']);
            $tabla->unique('code');
        });
    }
};
