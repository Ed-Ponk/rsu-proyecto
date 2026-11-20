<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avance 03: grupos de personal (plantillas) y programaciones (una fila por día de trabajo).
     * Los únicos de las programaciones impiden en la base de datos que una persona o un vehículo
     * queden asignados dos veces el mismo día y turno, aunque dos usuarios guarden a la vez.
     */
    public function up(): void
    {
        Schema::create('staffgroups', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100)->unique();
            $tabla->foreignId('shift_id')->constrained('schedules')->restrictOnDelete();
            $tabla->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $tabla->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $tabla->json('weekdays');                                   // días ISO: 1 = lunes ... 7 = domingo
            $tabla->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $tabla->boolean('status')->default(true);
            $tabla->timestamps();
        });

        Schema::create('staffgroup_helpers', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('group_id')->constrained('staffgroups')->cascadeOnDelete();
            $tabla->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $tabla->unsignedTinyInteger('position');
            $tabla->unique(['group_id', 'user_id']);
            $tabla->unique(['group_id', 'position']);
        });

        Schema::create('programmings', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->date('date');
            $tabla->foreignId('group_id')->nullable()->constrained('staffgroups')->nullOnDelete();
            $tabla->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $tabla->foreignId('shift_id')->constrained('schedules')->restrictOnDelete();
            $tabla->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $tabla->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $tabla->string('status', 12)->default('programado');       // programado | finalizado
            $tabla->text('notes')->nullable();
            $tabla->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamps();

            $tabla->unique(['date', 'shift_id', 'driver_id'], 'programmings_conductor_unico');
            $tabla->unique(['date', 'shift_id', 'vehicle_id'], 'programmings_vehiculo_unico');
            $tabla->index(['date', 'zone_id']);
        });

        Schema::create('programming_helpers', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('programming_id')->constrained('programmings')->cascadeOnDelete();
            $tabla->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $tabla->date('date');                                       // copia de la programación: permite el único por persona
            $tabla->foreignId('shift_id')->constrained('schedules')->restrictOnDelete();
            $tabla->unsignedTinyInteger('position');

            $tabla->unique(['programming_id', 'position']);
            $tabla->unique(['user_id', 'date', 'shift_id'], 'programming_helpers_persona_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programming_helpers');
        Schema::dropIfExists('programmings');
        Schema::dropIfExists('staffgroup_helpers');
        Schema::dropIfExists('staffgroups');
    }
};
