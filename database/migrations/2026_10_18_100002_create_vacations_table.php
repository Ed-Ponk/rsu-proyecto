<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Solicitudes de vacaciones. Solo las aprobadas descuentan días y sacan al personal de las programaciones. */
    public function up(): void
    {
        Schema::create('vacations', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $tabla->date('start_date');
            $tabla->date('end_date');
            $tabla->unsignedSmallInteger('days');                    // días solicitados (corridos, inicio y fin incluidos)
            $tabla->string('status', 10)->default('pendiente');      // pendiente | aprobada | rechazada
            $tabla->text('notes')->nullable();
            $tabla->timestamps();

            $tabla->index(['user_id', 'status']);
            $tabla->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacations');
    }
};
