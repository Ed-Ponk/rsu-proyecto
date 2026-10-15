<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Turnos de trabajo. La asistencia deduce el turno según la hora; el CRUD de turnos llega en el Avance 3. */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 50)->unique();
            $tabla->time('time_start');
            $tabla->time('time_end');                 // si time_end <= time_start el turno cruza la medianoche
            $tabla->text('description')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
