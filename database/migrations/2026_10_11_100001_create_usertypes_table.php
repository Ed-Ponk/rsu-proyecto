<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usertypes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100)->unique();
            $tabla->string('description')->nullable();
            // Funciones predefinidas (Conductor y Ayudante): no se eliminan ni se renombran.
            $tabla->boolean('is_system')->default(false);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usertypes');
    }
};
