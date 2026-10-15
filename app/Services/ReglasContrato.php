<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Personal;
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
            ->whereDate('start_date', '<', $inicio->toDateString())
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
}
