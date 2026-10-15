<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicletypes', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100)->unique();
            $tabla->text('description')->nullable();
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicletypes');
    }
};
