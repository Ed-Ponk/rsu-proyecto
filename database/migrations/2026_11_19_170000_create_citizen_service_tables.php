<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $avance07 = DB::table('migrations')->where('migration', '2026_11_19_100001_create_citizens_table')->exists()
            && DB::table('migrations')->where('migration', '2026_11_19_100002_create_trip_ratings_table')->exists();
        if ($avance07 && (! Schema::hasColumns('citizens', ['id', 'dni', 'email', 'password', 'status', 'zone_id'])
            || ! Schema::hasColumns('trip_ratings', ['id', 'trip_id', 'citizen_id', 'rating']))) {
            throw new RuntimeException('El esquema de ciudadanía de Avance 07 no coincide con el esperado.');
        }
        if (! $avance07) {
            $this->crearCiudadanosYValoraciones();
        }
        Schema::create('citizen_zone_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->index(['citizen_id', 'zone_id', 'started_at']);
        });
        Schema::table('trip_points', function (Blueprint $table) {
            $table->uuid('operation_id')->nullable();
            $table->unique(['trip_id', 'operation_id']);
        });
    }

    private function crearCiudadanosYValoraciones(): void
    {
        Schema::create('citizens', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 8)->unique();
            $table->string('name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->foreignId('zone_id')->constrained('zones')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('trip_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'citizen_id']);
        });
    }

    public function down(): void
    {
        Schema::table('trip_points', function (Blueprint $table) {
            $table->dropUnique(['trip_id', 'operation_id']);
            $table->dropColumn('operation_id');
        });
        Schema::dropIfExists('trip_ratings');
        Schema::dropIfExists('citizen_zone_periods');
        if (DB::table('migrations')->where('migration', '2026_11_19_100001_create_citizens_table')->exists()) {
            return;
        }
        Schema::dropIfExists('citizens');
    }
};
