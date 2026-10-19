<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Divisiones territoriales (departamento → provincia → distrito) que alimentan los combos de las zonas. */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 60)->unique();
            $tabla->timestamps();
        });

        Schema::create('provinces', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $tabla->string('name', 60);
            $tabla->timestamps();

            $tabla->unique(['department_id', 'name']);
        });

        Schema::create('districts', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('province_id')->constrained('provinces')->restrictOnDelete();
            $tabla->string('name', 80);
            $tabla->decimal('latitude', 10, 7);    // centro aproximado: sirve para centrar el mapa
            $tabla->decimal('longitude', 10, 7);
            $tabla->timestamps();

            $tabla->unique(['province_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('departments');
    }
};
