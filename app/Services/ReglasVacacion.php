<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Vacacion;
use Illuminate\Support\Carbon;

/**
 * Reglas de negocio de las solicitudes de vacaciones.
 * `revisar()` devuelve los mensajes de error indexados por campo; un arreglo vacío significa que todo está bien.
 */
class ReglasVacacion
{
    /** Tipos de contrato que dan derecho a vacaciones. */
    public const CONTRATOS_CON_DERECHO = [Contrato::NOMBRADO, Contrato::PERMANENTE];

    /** @return array<string, string> */
    public function revisar(array $datos, ?Vacacion $actual = null): array
    {
        $persona = Personal::find($datos['user_id'] ?? null);
        $dias = (int) ($datos['days'] ?? 0);
        $inicio = filled($datos['start_date'] ?? null) ? Carbon::parse($datos['start_date'])->startOfDay() : null;

        if (! $persona || ! $inicio || $dias < 1) {
            return [];
        }

        $errores = [];

        if (! $persona->status) {
            $errores['user_id'] = 'No se puede registrar vacaciones de una persona inactiva.';
        } elseif (! $this->tieneDerecho($persona, $inicio)) {
            $errores['user_id'] = 'Solo el personal nombrado o con contrato permanente vigente puede solicitar vacaciones.';
        }

        // Al editar sin cambiar la fecha de inicio no se vuelve a exigir que sea futura.
        $cambioInicio = ! $actual || ! $actual->start_date->isSameDay($inicio);
        if ($cambioInicio && $inicio->lt(today())) {
            $errores['start_date'] = 'La fecha de inicio no puede ser anterior a hoy.';
        }

        $fin = Vacacion::fechaFin($inicio, $dias);

        $choque = Vacacion::ocupaFechas()
            ->where('user_id', $persona->id)
            ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))
            ->queSeCruzaCon($inicio, $fin)
            ->first();

        if ($choque) {
            $errores['start_date'] ??= 'Las fechas coinciden con otra solicitud '.strtolower(Vacacion::ESTADOS[$choque->status])
                .' ('.$choque->start_date->format('d/m/Y').' al '.$choque->end_date->format('d/m/Y').').';
        }

        $disponibles = $this->disponiblesParaAprobar($persona->id, $inicio->year, $actual?->id);
        if ($dias > $disponibles) {
            $errores['days'] = $disponibles === 0
                ? "No le quedan días de vacaciones en {$inicio->year}."
                : "Solo le quedan {$disponibles} día(s) de vacaciones en {$inicio->year}.";
        }

        return $errores;
    }

    /** ¿Tiene un contrato nombrado o permanente vigente en esa fecha? */
    public function tieneDerecho(Personal $persona, Carbon $fecha): bool
    {
        return Contrato::vigente($fecha)
            ->where('user_id', $persona->id)
            ->whereIn('type', self::CONTRATOS_CON_DERECHO)
            ->exists();
    }

    /** Cupo anual menos lo ya aprobado (sin contar la solicitud que se está editando). */
    public function disponiblesParaAprobar(int $idPersonal, int $anio, ?int $ignorarId = null): int
    {
        return max(0, (int) config('proyecto.vacaciones.dias_por_anio') - Vacacion::diasAprobados($idPersonal, $anio, $ignorarId));
    }
}
