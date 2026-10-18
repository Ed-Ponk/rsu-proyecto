<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * shift_date:   día en que EMPEZÓ el turno al que pertenece la marcación. En el turno noche, la salida de
     *               las 06:00 del día 18 pertenece al turno del 17. Con (user_id, schedule_id, shift_date) se
     *               agrupa la entrada y la salida de un mismo turno.
     * late_minutes: minutos de tardanza de una entrada (0 si llegó a tiempo o dentro de la tolerancia; null en salidas y ausencias).
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $tabla) {
            $tabla->date('shift_date')->nullable()->after('schedule_id');
            $tabla->unsignedSmallInteger('late_minutes')->nullable()->after('type');
            $tabla->index(['user_id', 'schedule_id', 'shift_date']);
        });

        // Registros previos: su turno empezó el mismo día de la marcación.
        // "date" se envuelve con la gramática del motor (`date` en MySQL, "date" en SQLite): es palabra clave en MySQL.
        DB::table('attendances')->whereNull('shift_date')
            ->update(['shift_date' => DB::raw(DB::getQueryGrammar()->wrap('date'))]);
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $tabla) {
            $tabla->dropIndex(['user_id', 'schedule_id', 'shift_date']);
            $tabla->dropColumn(['shift_date', 'late_minutes']);
        });
    }
};
