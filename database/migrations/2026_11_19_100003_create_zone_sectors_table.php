<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Sectores (barrios o urbanizaciones) que cubre una zona; los carga el administrador. */
    public function up(): void
    {
        Schema::create('zone_sectors', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $tabla->string('name', 100);
            $tabla->unsignedSmallInteger('position')->default(1);
            $tabla->timestamps();

            $tabla->unique(['zone_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_sectors');
    }
};
