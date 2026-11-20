<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * audit_logs: quién creó, modificó o eliminó cada registro importante (asistencias, contratos, vacaciones...).
     * vacations.resolved_by / resolved_at: quién aprobó o rechazó la solicitud y cuándo.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->string('action', 12);                 // creado | modificado | eliminado
            $tabla->string('model', 40);
            $tabla->unsignedBigInteger('model_id');
            $tabla->text('before')->nullable();           // JSON con los valores anteriores
            $tabla->text('after')->nullable();            // JSON con los valores nuevos
            $tabla->timestamp('created_at')->useCurrent();

            $tabla->index(['model', 'model_id']);
            $tabla->index('created_at');
        });

        Schema::table('vacations', function (Blueprint $tabla) {
            $tabla->foreignId('resolved_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $tabla->timestamp('resolved_at')->nullable()->after('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('resolved_by');
            $tabla->dropColumn('resolved_at');
        });

        Schema::dropIfExists('audit_logs');
    }
};
