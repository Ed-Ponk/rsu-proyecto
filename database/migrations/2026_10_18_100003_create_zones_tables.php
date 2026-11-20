<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Zonas de recolección: cada una es un polígono formado por sus coordenadas (mínimo 3). */
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100)->unique();
            $tabla->string('department', 60);
            $tabla->string('province', 60);
            $tabla->string('district', 80);
            $tabla->text('description')->nullable();
            $tabla->decimal('average_waste', 10, 2)->nullable();   // kg de residuos por día
            $tabla->boolean('status')->default(true);
            $tabla->timestamps();

            $tabla->index(['department', 'province', 'district']);
        });

        Schema::create('zonecoords', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $tabla->decimal('latitude', 10, 7);
            $tabla->decimal('longitude', 10, 7);
            $tabla->unsignedSmallInteger('position');              // orden en que se unen los vértices
            $tabla->timestamps();

            $tabla->index(['zone_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zonecoords');
        Schema::dropIfExists('zones');
    }
};
