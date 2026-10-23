<?php

namespace App\Console\Commands;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Turno;
use App\Models\Vacacion;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Services\ReglasZona;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Diagnóstico de solo lectura: nunca modifica ni elimina datos. */
class VerificarIntegridad extends Command
{
    protected $signature = 'rsu:verificar-integridad';

    protected $description = 'Informa identificadores y reglas incumplidas sin mostrar datos personales';

    public function handle(): int
    {
        $problemas = [];
        $informar = function ($tipo, $id, $motivo) use (&$problemas) {
            $problemas[] = [$tipo, $id, $motivo];
        };
        if (! DB::table('users')->whereNull('usertype_id')->where('status', true)->exists()) {
            $informar('administradores', '-', 'No queda ningún administrador activo');
        }
        foreach (Personal::with('contratos')->cursor() as $persona) {
            $activos = $persona->contratos->where('is_active', true);
            if ($activos->count() > 1 || (! $persona->status && $activos->isNotEmpty())) {
                $informar('users', $persona->id, 'Contratos activos incompatibles con el estado');
            }
            foreach ($persona->contratos as $contrato) {
                if ($persona->birthdate && $contrato->start_date->lt($persona->birthdate->copy()->addYears(config('proyecto.personal.edad_minima')))) {
                    $informar('contracts', $contrato->id, 'Inicio anterior a la edad mínima');
                }
            }
        }
        foreach (Contrato::where('type', Contrato::TEMPORAL)->cursor() as $contrato) {
            if (! $contrato->end_date || $contrato->end_date->lte($contrato->start_date)) {
                $informar('contracts', $contrato->id, 'Fechas temporales inválidas');
            }
            $otros = Contrato::where('user_id', $contrato->user_id)->where('type', Contrato::TEMPORAL)->where('id', '>', $contrato->id)->get();
            foreach ($otros as $otro) {
                if ($contrato->end_date && $otro->end_date && ! ($contrato->end_date->copy()->addMonthsNoOverflow(config('proyecto.contratos.meses_entre_temporales'))->lte($otro->start_date)
                    || $otro->end_date->copy()->addMonthsNoOverflow(config('proyecto.contratos.meses_entre_temporales'))->lte($contrato->start_date))) {
                    $informar('contracts', $contrato->id, 'Separación insuficiente frente a #'.$otro->id);
                }
            }
        }
        foreach (Vacacion::ocupaFechas()->cursor() as $vacacion) {
            if ($vacacion->days < 1 || ! Vacacion::fechaFin($vacacion->start_date, $vacacion->days)->isSameDay($vacacion->end_date)) {
                $informar('vacations', $vacacion->id, 'Duración incoherente');
            }
            if (Vacacion::ocupaFechas()->where('user_id', $vacacion->user_id)->where('id', '>', $vacacion->id)->queSeCruzaCon($vacacion->start_date, $vacacion->end_date)->exists()) {
                $informar('vacations', $vacacion->id, 'Solicitudes superpuestas');
            }
            if ($vacacion->status === Vacacion::APROBADA && Asistencia::where('user_id', $vacacion->user_id)->whereBetween('date', [$vacacion->start_date, $vacacion->end_date])->exists()) {
                $informar('vacations', $vacacion->id, 'Asistencia dentro de vacaciones aprobadas');
            }
        }
        foreach (Vacacion::diasAprobadosAgrupados() as $clave => $dias) {
            if ($dias > config('proyecto.vacaciones.dias_por_anio')) {
                $informar('vacations', $clave, 'Cupo anual excedido');
            }
        }
        foreach (Vehiculo::with('imagenes')->cursor() as $vehiculo) {
            if ($vehiculo->imagenes->count() > config('proyecto.vehiculos.max_imagenes') || ($vehiculo->imagenes->isNotEmpty() && $vehiculo->imagenes->where('profile', true)->count() !== 1)) {
                $informar('vehicles', $vehiculo->id, 'Cantidad de imágenes o perfil inválido');
            }
            if (! DB::table('brandmodels')->where('id', $vehiculo->model_id)->where('brand_id', $vehiculo->brand_id)->exists()) {
                $informar('vehicles', $vehiculo->id, 'Modelo de otra marca');
            }
        }
        $reglas = app(ReglasZona::class);
        foreach (Zona::with('coordenadas')->cursor() as $zona) {
            $puntos = $zona->coordenadas->map->only(['latitude', 'longitude'])->all();
            if ($reglas->revisarPerimetro($puntos, $zona->department) || $reglas->mensajeDeInvasion($puntos, $zona->id)) {
                $informar('zones', $zona->id, 'Perímetro inválido o invasión');
            }
        }
        $turnos = Turno::orderBy('id')->get();
        $tramos = fn ($t) => $t->time_end > $t->time_start ? [[$t->time_start, $t->time_end]] : [[$t->time_start, '24:00:00'], ['00:00:00', $t->time_end]];
        foreach ($turnos as $turno) {
            foreach ($turnos->where('id', '>', $turno->id) as $otro) {
                foreach ($tramos($turno) as [$a,$b]) {
                    foreach ($tramos($otro) as [$c,$d]) {
                        if ($a < $d && $c < $b) {
                            $informar('schedules', $turno->id, 'Cruce con #'.$otro->id);
                        }
                    }
                }
            }
        }
        foreach (DB::table('attendances')->select('user_id', 'date')->groupBy('user_id', 'date')->havingRaw("SUM(CASE WHEN status = 'ausente' THEN 1 ELSE 0 END) > 0 AND COUNT(*) > 1")->get() as $grupo) {
            $informar('attendances', $grupo->user_id.':'.$grupo->date, 'Ausencia duplicada o mezclada con presencia');
        }
        if ($problemas) {
            $this->table(['Entidad', 'Identificador', 'Problema'], $problemas);
        }
        $this->info(count($problemas).' inconsistencia(s). No se modificaron datos.');

        return $problemas ? self::FAILURE : self::SUCCESS;
    }
}
