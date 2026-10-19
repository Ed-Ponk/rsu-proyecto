<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * shift_start: hora de inicio que tenía el turno cuando se marcó. Con ella la tardanza de una marcación antigua
     *              no cambia si después se edita el turno.
     * Índice único (usuario, turno, jornada, tipo): la base impide dos entradas o dos salidas en el mismo turno.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $tabla) {
            $tabla->time('shift_start')->nullable()->after('shift_date');
        });

        foreach (DB::table('schedules')->get(['id', 'time_start']) as $turno) {
            DB::table('attendances')->where('schedule_id', $turno->id)->whereNull('shift_start')->update(['shift_start' => $turno->time_start]);
        }

        Schema::table('attendances', function (Blueprint $tabla) {
            $tabla->unique(['user_id', 'schedule_id', 'shift_date', 'type'], 'attendances_turno_tipo_unico');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $tabla) {
            $tabla->dropUnique('attendances_turno_tipo_unico');
            $tabla->dropColumn('shift_start');
        });
    }
};
