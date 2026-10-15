<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100);
            $tabla->string('code', 50)->unique();
            $tabla->string('plate', 10)->unique();
            $tabla->unsignedSmallInteger('year');
            $tabla->decimal('load_capacity', 8, 2)->default(0);        // Toneladas
            $tabla->decimal('fuel_capacity', 8, 2)->default(0);        // Litros
            $tabla->decimal('compact_capacity', 8, 2)->default(0);     // Toneladas
            $tabla->unsignedSmallInteger('occupant_capacity')->default(1); // Personas que se pueden asignar
            $tabla->text('description')->nullable();
            $tabla->boolean('status')->default(true);
            $tabla->foreignId('brand_id')->constrained('brands');
            $tabla->foreignId('model_id')->constrained('brandmodels');
            $tabla->foreignId('type_id')->constrained('vehicletypes');
            $tabla->foreignId('color_id')->constrained('vehiclecolors');
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
