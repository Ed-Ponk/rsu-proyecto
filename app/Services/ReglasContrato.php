<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Vacacion;
use Illuminate\Support\Carbon;

/**
 * Reglas de negocio de los contratos del personal.
 * `revisar()` devuelve los mensajes de error indexados por campo; un arreglo vacío significa que todo está bien.
 */
class ReglasContrato
{
    /** @return array<string, string> */
    public function revisar(array $datos, ?Contrato $actual = null): array
    {
        $persona = Personal::find($datos['user_id'] ?? null);
        $tipo = $datos['type'] ?? null;
        $inicio = filled($datos['start_date'] ?? null) ? Carbon::parse($datos['start_date']) : null;
        $fin = filled($datos['end_date'] ?? null) ? Carbon::parse($datos['end_date']) : null;
        $activo = (bool) ($datos['is_active'] ?? false);

        if (! $persona || ! $inicio || ! $tipo) {
            return [];
        }

        $errores = [];

        if (! $persona->status) {
            $errores['user_id'] = 'No se puede contratar a una persona inactiva.';
        }

        $edadMinima = (int) config('proyecto.personal.edad_minima');
        if ($persona->birthdate && $inicio->lt($persona->birthdate->copy()->addYears($edadMinima))) {
            $errores['start_date'] = "La persona cumple {$edadMinima} años el ".$persona->birthdate->copy()->addYears($edadMinima)->format('d/m/Y').': el contrato no puede empezar antes.';
        }

        if ($tipo === Contrato::TEMPORAL) {
            if (! $fin) {
                $errores['end_date'] = 'El contrato temporal necesita fecha de término.';
            } elseif ($fin->lessThanOrEqualTo($inicio)) {
                $errores['end_date'] = 'La fecha de término debe ser posterior a la de inicio.';
            }
        }

        if ($activo && $fin?->lt(today())) {
            $errores['is_active'] = 'El contrato ya venció, no puede quedar activo.';
        }

        if ($activo && ($otro = $this->contratoActivoDe($persona, $actual))) {
            $errores['user_id'] = 'Esta persona ya tiene un contrato activo ('
                .(Contrato::TIPOS[$otro->type] ?? $otro->type).' desde '.$otro->start_date->format('d/m/Y')
                .'). Desactívelo antes de registrar otro.';
        }

        if ($tipo === Contrato::TEMPORAL && ! isset($errores['user_id'])) {
            $errorPlazo = $this->validarPlazoEntreTemporales($persona, $inicio, $actual);

            if ($errorPlazo) {
                $errores['start_date'] = $errorPlazo;
            }
        }

        return $errores;
    }

    private function contratoActivoDe(Personal $persona, ?Contrato $excepto): ?Contrato
    {
        return Contrato::where('user_id', $persona->id)
            ->where('is_active', true)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto->id))
            ->first();
    }

    /** Entre dos temporales de la misma persona deben pasar N meses desde que venció el anterior. */
    private function validarPlazoEntreTemporales(Personal $persona, Carbon $inicio, ?Contrato $excepto): ?string
    {
        $meses = (int) config('proyecto.contratos.meses_entre_temporales', 2);

        $anterior = Contrato::where('user_id', $persona->id)
            ->where('type', Contrato::TEMPORAL)
            ->whereNotNull('end_date')
            ->where('start_date', '<', $inicio->toDateString())
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto->id))
            ->orderByDesc('end_date')
            ->first();

        if (! $anterior) {
            return null;
        }

        $primerDiaPermitido = $anterior->end_date->copy()->addMonths($meses);

        if ($inicio->gte($primerDiaPermitido)) {
            return null;
        }

        return "El último temporal venció el {$anterior->end_date->format('d/m/Y')}. Deben pasar {$meses} meses: "
            ."la nueva fecha de inicio debe ser el {$primerDiaPermitido->format('d/m/Y')} o posterior.";
    }

    /**
     * Antes de cambiar o desactivar un contrato: ¿algo depende de él? Devuelve el mensaje del problema o null.
     *  - Vacaciones pendientes o aprobadas que todavía no terminan y quedarían sin derecho (contrato nombrado/permanente).
     *  - Asistencias que quedarían fuera de las nuevas fechas del contrato (solo si cambian las fechas).
     *
     * @param  array{type: string, start_date: string, end_date: ?string, is_active: bool}  $nuevo  cómo quedaría el contrato
     */
    public function revisarDependientes(Contrato $actual, array $nuevo, bool $revisarAsistencias = true): ?string
    {
        $inicio = Carbon::parse($nuevo['start_date'])->startOfDay();
        $fin = filled($nuevo['end_date'] ?? null) ? Carbon::parse($nuevo['end_date'])->startOfDay() : null;
        $activo = (bool) ($nuevo['is_active'] ?? false);

        $otros = Contrato::where('user_id', $actual->user_id)->whereKeyNot($actual->id)->get();

        $cubreVacaciones = function (Carbon $dia) use ($nuevo, $inicio, $fin, $activo, $otros): bool {
            $propio = $activo
                && in_array($nuevo['type'], ReglasVacacion::CONTRATOS_CON_DERECHO, true)
                && $inicio->lte($dia) && (! $fin || $fin->gte($dia));

            return $propio || $otros->contains(fn (Contrato $c) => $c->is_active
                && in_array($c->type, ReglasVacacion::CONTRATOS_CON_DERECHO, true)
                && $c->start_date->lte($dia) && (! $c->end_date || $c->end_date->gte($dia)));
        };

        $vacaciones = Vacacion::where('user_id', $actual->user_id)
            ->whereIn('status', [Vacacion::PENDIENTE, Vacacion::APROBADA])
            ->where('end_date', '>=', today()->toDateString())
            ->orderBy('start_date')->get();

        foreach ($vacaciones as $vacacion) {
            if (! $cubreVacaciones($vacacion->start_date)) {
                return 'Tiene vacaciones '.strtolower(Vacacion::ESTADOS[$vacacion->status]).' del '.$vacacion->start_date->format('d/m/Y')
                    .' al '.$vacacion->end_date->format('d/m/Y').' que dependen de este contrato. Rechace o elimine esas solicitudes primero.';
            }
        }

        if (! $revisarAsistencias) {
            return null;
        }

        $fuera = Asistencia::where('user_id', $actual->user_id)
            ->where(fn ($q) => $q->where('date', '<', $inicio->toDateString())
                ->when($fin, fn ($w) => $w->orWhere('date', '>', $fin->toDateString())))
            ->orderBy('date')->get(['date'])
            ->first(fn ($a) => ! $otros->contains(fn (Contrato $c) => $c->start_date->lte($a->date) && (! $c->end_date || $c->end_date->gte($a->date))));

        if ($fuera) {
            return 'Hay asistencias registradas fuera de las nuevas fechas del contrato (por ejemplo, el '.$fuera->date->format('d/m/Y').'). Corrija esas asistencias o ajuste las fechas.';
        }

        return null;
    }
}
