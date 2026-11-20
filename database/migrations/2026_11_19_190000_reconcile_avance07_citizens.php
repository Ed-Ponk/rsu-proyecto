<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('citizens', 'status')) {
            Schema::table('citizens', fn (Blueprint $table) => $table->boolean('status')->default(true));
        }
        if (Schema::hasColumn('trip_ratings', 'rating') && ! Schema::hasColumn('trip_ratings', 'stars')) {
            Schema::table('trip_ratings', fn (Blueprint $table) => $table->renameColumn('rating', 'stars'));
        }
        if (DB::table('migrations')->where('migration', '2026_11_19_100001_create_citizens_table')->exists()) {
            $ahora = now();
            DB::table('citizen_zone_periods')->insertUsing(['citizen_id', 'zone_id', 'started_at'],
                DB::table('citizens')->select('id', 'zone_id')->selectRaw('? as started_at', [$ahora])
                    ->whereNotExists(DB::table('citizen_zone_periods')->selectRaw('1')->whereColumn('citizen_id', 'citizens.id')));
            DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\Ciudadano')->delete();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restaura el respaldo previo para revertir la conciliación de ciudadanos.');
    }
};
