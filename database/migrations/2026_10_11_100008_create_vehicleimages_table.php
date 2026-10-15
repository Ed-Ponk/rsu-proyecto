<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicleimages', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('image', 1000);
            $tabla->boolean('profile')->default(false);   // imagen de perfil (principal)
            $tabla->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicleimages');
    }
};
