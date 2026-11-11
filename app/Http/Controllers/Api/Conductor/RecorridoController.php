<?php

namespace App\Http\Controllers\Api\Conductor;

use App\Http\Controllers\Controller;
use App\Models\Programacion;
use App\Models\PuntoRecorrido;
use App\Models\Recorrido;
use App\Services\ServicioConductor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Iniciar, registrar la trayectoria, finalizar y consultar los recorridos del conductor. */
class RecorridoController extends Controller
{
    public function __construct(private ServicioConductor $servicio) {}

    /** Inicia el recorrido: solo con el turno vigente y todo el personal presente. */
    public function iniciar(Request $request, int $programacion): JsonResponse
    {
        $conductor = $request->attributes->get('conductor');

        $recorrido = DB::transaction(function () use ($conductor, $programacion) {
            // Bloquea la programación: dos toques seguidos en «Iniciar» no crean dos recorridos.
            $p = Programacion::where('driver_id', $conductor->id)->lockForUpdate()->find($programacion);
            abort_if(! $p, 404, 'No se encontró la programación.');
            $p->load(['turno', 'conductor', 'ayudantes', 'recorrido']);

            if (Recorrido::activos()->where('driver_id', $conductor->id)->exists() && ! $p->recorrido?->enCurso()) {
                abort(422, 'Ya tiene otro recorrido en curso. Finalícelo antes de iniciar uno nuevo.');
            }

            [$puede, $motivo] = $this->servicio->puedeIniciar($p);
            abort_if(! $puede, 422, $motivo);

            return Recorrido::create([
                'programming_id' => $p->id,
                'driver_id' => $conductor->id,
                'status' => Recorrido::EN_CURSO,
                'started_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Recorrido iniciado.',
            'data' => $this->servicio->recorridoDetalle($recorrido->load(['programacion.zona', 'programacion.turno', 'programacion.vehiculo', 'programacion.ayudantes', 'puntos'])),
        ], 201);
    }

    /** Recibe uno o varios puntos GPS (la app los acumula si se queda sin señal). */
    public function puntos(Request $request, int $recorrido): JsonResponse
    {
        $r = $this->delConductor($request, $recorrido);
        abort_if(! $r->enCurso(), 422, 'El recorrido ya fue finalizado: no se registran más ubicaciones.');

        $maximo = (int) config('proyecto.recorridos.max_puntos_por_lote', 500);
        $datos = $request->validate([
            'puntos' => ['required', 'array', 'min:1', "max:{$maximo}"],
            'puntos.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'puntos.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'puntos.*.precision' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'puntos.*.momento' => ['required', 'date'],
        ]);

        // Solo puntos tomados durante el recorrido (con un minuto de margen por la diferencia de relojes).
        $desde = $r->started_at->copy()->subMinute();
        $hasta = now()->addMinute();
        $filas = [];

        foreach ($datos['puntos'] as $punto) {
            $momento = Carbon::parse($punto['momento'])->setTimezone(config('app.timezone'));

            if ($momento->lt($desde) || $momento->gt($hasta)) {
                continue;
            }

            $filas[] = [
                'trip_id' => $r->id,
                'latitude' => round((float) $punto['lat'], 7),
                'longitude' => round((float) $punto['lng'], 7),
                'accuracy' => isset($punto['precision']) ? round((float) $punto['precision'], 2) : null,
                'recorded_at' => $momento->format('Y-m-d H:i:s'),
                'created_at' => now(),
            ];
        }

        if ($filas) {
            PuntoRecorrido::insert($filas);
        }

        return response()->json(['registrados' => count($filas), 'descartados' => count($datos['puntos']) - count($filas)]);
    }

    /** Cierra el recorrido con la observación del conductor y detiene el registro de coordenadas. */
    public function finalizar(Request $request, int $recorrido): JsonResponse
    {
        $maximo = (int) config('proyecto.recorridos.observacion_max', 500);
        $datos = $request->validate([
            'observacion' => ['required', 'string', 'min:3', "max:{$maximo}"],
        ], [], ['observacion' => 'observación']);

        $r = DB::transaction(function () use ($request, $recorrido, $datos) {
            $r = $this->delConductor($request, $recorrido, bloquear: true);
            abort_if(! $r->enCurso(), 422, 'Este recorrido ya fue finalizado.');

            $r->update([
                'status' => Recorrido::COMPLETADO,
                'ended_at' => now(),
                'observation' => trim($datos['observacion']),
            ]);

            return $r;
        });

        return response()->json([
            'message' => 'Recorrido finalizado correctamente.',
            'data' => $this->servicio->recorridoDetalle($r->load(['programacion.zona', 'programacion.turno', 'programacion.vehiculo', 'programacion.ayudantes', 'puntos'])),
        ]);
    }

    /** Historial: recorridos del conductor, del más reciente al más antiguo. */
    public function index(Request $request): JsonResponse
    {
        $pagina = Recorrido::with(['programacion.zona', 'programacion.turno', 'programacion.vehiculo'])
            ->where('driver_id', $request->attributes->get('conductor')->id)
            ->orderByDesc('started_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Recorrido $r) => $this->servicio->recorridoResumen($r))->all(),
            'pagina' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ]);
    }

    /** Recorrido en curso (para retomarlo si la app se cerró). */
    public function actual(Request $request): JsonResponse
    {
        $r = Recorrido::activos()->where('driver_id', $request->attributes->get('conductor')->id)
            ->with(['programacion.zona', 'programacion.turno', 'programacion.vehiculo', 'programacion.ayudantes', 'puntos'])
            ->first();

        return response()->json(['data' => $r ? $this->servicio->recorridoDetalle($r) : null]);
    }

    public function show(Request $request, int $recorrido): JsonResponse
    {
        $r = $this->delConductor($request, $recorrido);
        $r->load(['programacion.zona.coordenadas', 'programacion.turno', 'programacion.vehiculo', 'programacion.ayudantes', 'puntos']);

        return response()->json(['data' => $this->servicio->recorridoDetalle($r)]);
    }

    private function delConductor(Request $request, int $id, bool $bloquear = false): Recorrido
    {
        $consulta = Recorrido::where('driver_id', $request->attributes->get('conductor')->id);
        $r = ($bloquear ? $consulta->lockForUpdate() : $consulta)->find($id);
        abort_if(! $r, 404, 'No se encontró el recorrido.');

        return $r;
    }
}
