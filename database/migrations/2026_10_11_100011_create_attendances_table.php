<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $tabla->date('date');
            $tabla->time('time');
            $tabla->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete(); // turno
            $tabla->string('type', 10)->nullable();            // entrada | salida (null si es ausencia)
            $tabla->string('status', 10)->default('presente'); // presente | ausente
            $tabla->string('source', 10)->default('admin');    // admin | marcacion
            $tabla->text('notes')->nullable();
            $tabla->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamps();

            $tabla->index(['user_id', 'date']);
            $tabla->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
