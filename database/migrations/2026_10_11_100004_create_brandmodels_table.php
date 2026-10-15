<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brandmodels', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('name', 100);
            $tabla->string('code', 30)->unique();
            $tabla->text('description')->nullable();
            $tabla->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $tabla->timestamps();

            $tabla->unique(['brand_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brandmodels');
    }
};
