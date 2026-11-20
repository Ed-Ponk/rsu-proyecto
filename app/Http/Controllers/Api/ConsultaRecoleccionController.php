<?php

namespace App\Http\Controllers\Api;

use App\Models\Programacion;
use App\Models\Recorrido;
use App\Models\Zona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDOException;

class ConsultaRecoleccionController
{
    public function salud(): JsonResponse
    {
        $respuesta = ['servicio' => 'rsu', 'version' => 3];

        try {
            DB::table('migrations')->select('id')->limit(1)->get();
        } catch (PDOException) {
            return response()->json($respuesta, 503)->header('Cache-Control', 'no-store');
        }

        return response()->json($respuesta)->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'zona' => ['nullable', 'integer', 'min:1'],
        ]);
        $zonas = Zona::where('status', true);
        $zona = isset($filtros['zona']) ? (clone $zonas)->findOrFail($filtros['zona']) : null;
        $programaciones = $zona
            ? Programacion::with(['turno', 'recorrido'])
                ->where('zone_id', $zona->id)
                ->where(fn ($query) => $query
                    ->whereBetween('date', [today()->subDay()->toDateString(), today()->addDays(7)->toDateString()])
                    ->orWhereHas('recorrido', fn ($recorrido) => $recorrido->activos()))
                ->orderBy('date')->orderBy('id')->get()
                ->filter(fn (Programacion $p) => $p->date->isToday() || $p->date->isFuture() || $p->fin()->isFuture() || $p->recorrido?->enCurso())
                ->sortBy(fn (Programacion $p) => $p->inicio()->timestamp)
                ->map(fn (Programacion $p) => [
                    'id' => $p->id,
                    'inicio' => $p->inicio()->toIso8601String(),
                    'fin' => $p->fin()->toIso8601String(),
                    'estado' => match ($p->recorrido?->status) {
                        Recorrido::EN_CURSO => 'en_ruta',
                        Recorrido::COMPLETADO => 'completado',
                        default => $p->esProgramado() && ! $p->haTerminado() ? $p->status : 'sin_confirmacion',
                    },
                ])->values()
            : [];

        return response()->json([
            'zonas' => $zonas->orderBy('name')->orderBy('id')->get(['id', 'name', 'district']),
            'programaciones' => $programaciones,
            'actualizado' => now()->toIso8601String(),
        ]);
    }
}
