<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLAS = ['users', 'contracts', 'attendances', 'vacations', 'vehicles', 'brands', 'brandmodels', 'vehicletypes', 'vehiclecolors', 'usertypes', 'schedules', 'zones', 'holidays'];

    public function up(): void
    {
        Schema::create('bloqueos', fn (Blueprint $tabla) => $tabla->string('recurso', 40)->primary());
        DB::table('bloqueos')->insert(array_map(fn ($recurso) => ['recurso' => $recurso], ['administradores', 'turnos', 'zonas']));
        foreach (self::TABLAS as $nombre) {
            Schema::table($nombre, fn (Blueprint $tabla) => $tabla->unsignedInteger('version')->default(1));
        }
        Schema::table('audit_logs', fn (Blueprint $tabla) => $tabla->string('actor_name')->nullable());
        DB::table('audit_logs')->whereNotNull('user_id')->orderBy('id')->each(function ($evento) {
            DB::table('audit_logs')->where('id', $evento->id)->update(['actor_name' => DB::table('users')->where('id', $evento->user_id)->value('name')]);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', fn (Blueprint $tabla) => $tabla->dropColumn('actor_name'));
        foreach (self::TABLAS as $nombre) {
            Schema::table($nombre, fn (Blueprint $tabla) => $tabla->dropColumn('version'));
        }
        Schema::dropIfExists('bloqueos');
    }
};
