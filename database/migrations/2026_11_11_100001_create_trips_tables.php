<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avance 6 (app del conductor): cada programación se ejecuta con un recorrido, que guarda
 * la hora de inicio y fin, la observación del conductor y la trayectoria GPS (un punto cada pocos segundos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $tabla) {
            $tabla->id();
            // Una programación tiene a lo más un recorrido.
            $tabla->foreignId('programming_id')->unique()->constrained('programmings')->restrictOnDelete();
            $tabla->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $tabla->string('status', 12)->default('en_curso');   // en_curso | completado
            $tabla->dateTime('started_at');
            $tabla->dateTime('ended_at')->nullable();
            $tabla->string('observation', 500)->nullable();
            $tabla->timestamps();

            $tabla->index(['driver_id', 'status']);
            $tabla->index('started_at');
        });

        Schema::create('trip_points', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $tabla->decimal('latitude', 10, 7);
            $tabla->decimal('longitude', 10, 7);
            $tabla->decimal('accuracy', 8, 2)->nullable();   // precisión del GPS en metros
            $tabla->dateTime('recorded_at');
            $tabla->timestamp('created_at')->nullable();

            $tabla->index(['trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_points');
        Schema::dropIfExists('trips');
    }
};
