<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Días feriados. Los activos afectan la programación de rutas; los inactivos se ignoran en las validaciones. */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->date('date')->unique();
            $tabla->string('description', 150);
            $tabla->boolean('status')->default(true);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
