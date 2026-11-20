<?php

namespace App\Http\Controllers\Api\Conductor;

use App\Http\Controllers\Controller;
use App\Models\Programacion;
use App\Services\ServicioConductor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** «Mis programaciones» y «Programación de hoy» del conductor que inició sesión. */
class ProgramacionController extends Controller
{
    public function __construct(private ServicioConductor $servicio) {}

    public function index(Request $request): JsonResponse
    {
        $conductor = $request->attributes->get('conductor');

        return response()->json([
            'conductor' => $this->servicio->perfil($conductor),
            'data' => $this->servicio->proximas($conductor)->map(fn (Programacion $p) => $this->servicio->resumen($p))->all(),
        ]);
    }

    public function hoy(Request $request): JsonResponse
    {
        $programacion = $this->servicio->deHoy($request->attributes->get('conductor'));

        return response()->json([
            'fecha' => today()->toDateString(),
            'ahora' => now()->toIso8601String(),
            'data' => $programacion ? $this->servicio->detalle($programacion) : null,
        ]);
    }

    public function show(Request $request, int $programacion): JsonResponse
    {
        $p = Programacion::with(['zona', 'turno', 'vehiculo', 'grupo', 'ayudantes', 'recorrido', 'conductor'])
            ->where('driver_id', $request->attributes->get('conductor')->id)
            ->find($programacion);

        abort_if(! $p, 404, 'No se encontró la programación.');

        return response()->json(['data' => $this->servicio->detalle($p)]);
    }
}
