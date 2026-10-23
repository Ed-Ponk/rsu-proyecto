<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $tabla) {
            $tabla->dropForeign(['schedule_id']);
            $tabla->foreign('schedule_id')->references('id')->on('schedules')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $tabla) {
            $tabla->dropForeign(['schedule_id']);
            $tabla->foreign('schedule_id')->references('id')->on('schedules')->nullOnDelete();
        });
    }
};
