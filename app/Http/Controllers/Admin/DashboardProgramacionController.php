<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Programacion;
use App\Models\Turno;
use App\Services\MonitorDeProgramacion;
use Illuminate\Http\Request;

/**
 * Dashboard de programaciones (Avance 04): por zona, cuántas están completas o incompletas en un día y quién falta.
 * «Ver detalles» abre el editor de la programación (turno, vehículo y personal) para reemplazar al personal faltante.
 */
class DashboardProgramacionController extends Controller
{
    public function __construct(private readonly MonitorDeProgramacion $monitor) {}

    public function index(Request $request)
    {
        if (! $request->ajax()) {
            return view('admin.dashboard.programaciones', [
                'fecha' => today()->toDateString(),
                'turnos' => Turno::orderBy('time_start')->get(['id', 'name']),
            ]);
        }

        [$fecha, $turnoId] = $this->filtros($request);
        $filas = $this->monitor->delDia($fecha, $turnoId);

        $hoy = today()->toDateString();

        return response()->json([
            'fecha' => $fecha,
            'kpis' => [
                'total' => $filas->count(),
                'completas' => $filas->where('completa', true)->count(),
                'incompletas' => $filas->where('completa', false)->count(),
                'faltantes' => $filas->sum('faltantes'),
            ],
            // Una tarjeta por programación (zona + grupo + turno + vehículo) con cuántos están presentes y cuántos faltan.
            'programaciones' => $filas->map(function (array $f) use ($fecha, $hoy) {
                $p = $f['programacion'];
                $personas = count($f['personal']);

                return [
                    'id' => $p->id,
                    'zona_id' => $p->zone_id,
                    'zona' => $p->zona?->name,
                    'grupo' => $p->grupo?->name ?? 'Sin grupo',
                    'turno' => $p->turno?->name,
                    'vehiculo' => $p->vehiculo?->name,
                    'placa' => $p->vehiculo?->plate,
                    'estado' => $p->status,
                    'estado_texto' => Programacion::ESTADOS[$p->status] ?? $p->status,
                    'completa' => $f['completa'],
                    'personas' => $personas,
                    'faltantes' => $f['faltantes'],
                    'presentes' => $personas - $f['faltantes'],
                    'futura' => $fecha > $hoy,
                ];
            })->sortBy([['zona', 'asc'], ['turno', 'asc']])->values(),
        ]);
    }

    /** @return array{0: string, 1: ?int} */
    private function filtros(Request $request): array
    {
        $datos = $request->validate(['fecha' => ['nullable', 'date'], 'turno_id' => ['nullable', 'integer']], ['fecha.date' => 'La fecha no es válida.']);

        return [$datos['fecha'] ?? today()->toDateString(), filled($datos['turno_id'] ?? null) ? (int) $datos['turno_id'] : null];
    }
}
