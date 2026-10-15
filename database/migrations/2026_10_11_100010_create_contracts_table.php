<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $tabla->string('type', 20);                   // nombrado | permanente | temporal
            $tabla->date('start_date');
            $tabla->date('end_date')->nullable();         // solo temporales
            $tabla->decimal('salary', 10, 2);
            $tabla->unsignedTinyInteger('probation_months')->nullable();
            $tabla->boolean('is_active')->default(true);
            $tabla->timestamps();

            $tabla->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
