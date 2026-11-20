<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avance 04: motivos de cambio y registro de cada cambio hecho a una programación (turno, vehículo, conductor o ayudante).
     * Cada cambio guarda el valor anterior y el nuevo (con una "foto" legible), para poder mostrarlo y, si el cambio se elimina,
     * restablecer el valor inicial de la programación.
     */
    public function up(): void
    {
        Schema::create('change_reasons', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100)->unique();
            $tabla->text('description')->nullable();
            $tabla->timestamps();
        });

        Schema::create('programming_changes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('programming_id')->constrained('programmings')->cascadeOnDelete();
            $tabla->foreignId('group_id')->nullable()->constrained('staffgroups')->nullOnDelete();   // copia: el historial del grupo sobrevive al cambio de plantilla
            $tabla->string('type', 12);                       // turno | vehiculo | conductor | ayudante
            $tabla->unsignedBigInteger('old_id')->nullable(); // id del turno, vehículo o persona anterior
            $tabla->unsignedBigInteger('new_id')->nullable();
            $tabla->string('old_label', 160);
            $tabla->string('new_label', 160);
            $tabla->json('old_data')->nullable();             // detalle legible (horas, placa, función...)
            $tabla->json('new_data')->nullable();
            $tabla->foreignId('reason_id')->nullable()->constrained('change_reasons')->nullOnDelete();
            $tabla->string('reason_name', 100)->nullable();   // copia del nombre: sigue visible si el motivo se elimina
            $tabla->text('description')->nullable();
            $tabla->string('batch_id', 36)->nullable()->index();   // los cambios de una misma operación masiva comparten lote
            $tabla->string('scope', 10)->default('individual');    // individual | masivo
            $tabla->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamps();

            $tabla->index(['programming_id', 'type']);
            $tabla->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programming_changes');
        Schema::dropIfExists('change_reasons');
    }
};
