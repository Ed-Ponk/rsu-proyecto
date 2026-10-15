<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiclecolors', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100)->unique();
            $tabla->string('code', 7)->default('#64748B');   // código RGB en hexadecimal (#RRGGBB)
            $tabla->text('description')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiclecolors');
    }
};
