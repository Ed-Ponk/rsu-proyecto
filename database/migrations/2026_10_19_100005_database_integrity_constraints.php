<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reglas que antes vivían solo en PHP y ahora también protege la base de datos:
     *  - código de color y (zona, posición) únicos;
     *  - un vehículo no puede tener un modelo de otra marca (clave foránea compuesta);
     *  - las claves en cascada pasan a RESTRICT: borrar a alguien o una marca fuera de los controladores ya no arrasa su historial;
     *  - CHECK de fechas, días y estados válidos.
     * Lo que SQLite (pruebas) no puede alterar se aplica solo en MySQL/MariaDB.
     */
    public function up(): void
    {
        Schema::table('vehiclecolors', fn (Blueprint $tabla) => $tabla->unique('code'));
        Schema::table('zonecoords', fn (Blueprint $tabla) => $tabla->unique(['zone_id', 'position']));

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('brandmodels', fn (Blueprint $tabla) => $tabla->unique(['id', 'brand_id'], 'brandmodels_id_marca_unico'));
        Schema::table('vehicles', function (Blueprint $tabla) {
            $tabla->foreign(['model_id', 'brand_id'], 'vehicles_modelo_marca_foreign')
                ->references(['id', 'brand_id'])->on('brandmodels')->restrictOnDelete();
        });

        foreach ([['contracts', 'user_id', 'users'], ['attendances', 'user_id', 'users'], ['vacations', 'user_id', 'users'], ['brandmodels', 'brand_id', 'brands']] as [$tabla, $columna, $padre]) {
            Schema::table($tabla, fn (Blueprint $t) => $t->dropForeign([$columna]));
            Schema::table($tabla, fn (Blueprint $t) => $t->foreign($columna)->references('id')->on($padre)->restrictOnDelete());
        }

        $checks = [
            'contracts' => [
                'contracts_fechas_ck' => 'end_date IS NULL OR end_date >= start_date',
                'contracts_tipo_ck' => "type IN ('nombrado','permanente','temporal')",
            ],
            'vacations' => [
                'vacations_fechas_ck' => 'end_date >= start_date',
                'vacations_dias_ck' => 'days > 0',
                'vacations_estado_ck' => "status IN ('pendiente','aprobada','rechazada')",
            ],
            'attendances' => [
                'attendances_estado_ck' => "status IN ('presente','ausente')",
                'attendances_origen_ck' => "source IN ('admin','marcacion')",
                'attendances_tipo_ck' => "type IS NULL OR type IN ('entrada','salida')",
            ],
        ];

        foreach ($checks as $tabla => $reglas) {
            foreach ($reglas as $nombre => $condicion) {
                DB::statement("ALTER TABLE `{$tabla}` ADD CONSTRAINT `{$nombre}` CHECK ({$condicion})");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            foreach (['contracts' => ['contracts_fechas_ck', 'contracts_tipo_ck'], 'vacations' => ['vacations_fechas_ck', 'vacations_dias_ck', 'vacations_estado_ck'],
                'attendances' => ['attendances_estado_ck', 'attendances_origen_ck', 'attendances_tipo_ck']] as $tabla => $nombres) {
                foreach ($nombres as $nombre) {
                    DB::statement("ALTER TABLE `{$tabla}` DROP CONSTRAINT `{$nombre}`");
                }
            }

            foreach ([['contracts', 'user_id', 'users'], ['attendances', 'user_id', 'users'], ['vacations', 'user_id', 'users'], ['brandmodels', 'brand_id', 'brands']] as [$tabla, $columna, $padre]) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropForeign([$columna]));
                Schema::table($tabla, fn (Blueprint $t) => $t->foreign($columna)->references('id')->on($padre)->cascadeOnDelete());
            }

            Schema::table('vehicles', fn (Blueprint $t) => $t->dropForeign('vehicles_modelo_marca_foreign'));
            Schema::table('brandmodels', fn (Blueprint $t) => $t->dropUnique('brandmodels_id_marca_unico'));
        }

        Schema::table('zonecoords', fn (Blueprint $tabla) => $tabla->dropUnique(['zone_id', 'position']));
        Schema::table('vehiclecolors', fn (Blueprint $tabla) => $tabla->dropUnique(['code']));
    }
};
