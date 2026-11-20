<?php

namespace App\Http\Controllers\Api;

use App\Models\Citizen;
use App\Models\Programacion;
use App\Models\Recorrido;
use App\Models\Zona;
use App\Services\ReglasZona;
use App\Services\ServicioConductor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CiudadanoController
{
    public function registrar(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower($request->input('email'))]);
        }
        $datos = $request->validate([
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'unique:citizens,dni'],
            'name' => ['required', 'string', 'max:100', "regex:/^[\\pL\\pM .'-]+$/u"],
            'last_name' => ['required', 'string', 'max:100', "regex:/^[\\pL\\pM .'-]+$/u"],
            'email' => ['required', 'email', 'max:150', 'unique:citizens,email'],
            'password' => ['required', 'string', 'min:10', 'max:72', function ($field, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('La contraseña debe ocupar como máximo 72 bytes.');
                }
            }],
            'zone_id' => $this->zonaActiva(),
        ], [], [
            'dni' => 'DNI', 'name' => 'nombres', 'last_name' => 'apellidos', 'email' => 'correo electrónico',
            'password' => 'contraseña', 'zone_id' => 'zona',
        ]);

        $usuario = DB::transaction(function () use ($datos) {
            $usuario = Citizen::create($datos);
            $this->abrirPeriodo($usuario);

            return $usuario;
        });

        return response()->json($this->sesion($usuario), 201);
    }

    public function login(Request $request)
    {
        $datos = $request->validate(['email' => 'required|email|max:150', 'password' => 'required|string|max:72']);
        $usuario = Citizen::where('email', mb_strtolower($datos['email']))->first();
        if (! $usuario?->status || strlen($datos['password']) > 72 || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        return $this->sesion($usuario);
    }

    public function perfil(Request $request)
    {
        return ['ciudadano' => $request->user()];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function zonas()
    {
        return ['data' => Zona::where('status', true)->with(['coordenadas', 'sectores'])->orderBy('name')->orderBy('id')->get()
            ->map(fn (Zona $zona) => $this->formatoZona($zona))];
    }

    public function ubicar(Request $request, ReglasZona $reglas)
    {
        $datos = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);

        return ['data' => collect($this->zonas()['data'])
            ->filter(fn ($zona) => $reglas->contiene($zona['vertices'], (float) $datos['lat'], (float) $datos['lng'], true))->values()];
    }

    public function cambiarZona(Request $request)
    {
        $datos = $request->validate(['zone_id' => $this->zonaActiva()]);
        $usuario = DB::transaction(function () use ($request, $datos) {
            $usuario = Citizen::lockForUpdate()->findOrFail($request->user()->id);
            if ($usuario->zone_id !== (int) $datos['zone_id']) {
                DB::table('citizen_zone_periods')->where('citizen_id', $usuario->id)->whereNull('ended_at')->update(['ended_at' => now()]);
                $usuario->update($datos);
                $this->abrirPeriodo($usuario);
            }

            return $usuario;
        }, 3);

        return ['ciudadano' => $usuario];
    }

    public function programaciones(Request $request)
    {
        $datos = $request->validate(['mes' => 'nullable|date_format:Y-m', 'page' => 'nullable|integer|min:1']);
        $mes = isset($datos['mes']) ? Carbon::createFromFormat('!Y-m', $datos['mes']) : today()->startOfMonth();
        $pagina = Programacion::where('zone_id', $request->user()->zone_id)
            ->whereHas('zona', fn ($query) => $query->where('status', true))
            ->whereBetween('date', [$mes->toDateString(), $mes->copy()->endOfMonth()->toDateString()])
            ->with(['zona', 'turno', 'recorrido'])->orderBy('date')->orderBy('shift_id')->orderBy('id')->paginate(40);

        return $pagina->through(fn (Programacion $p) => $this->resumen($p));
    }

    public function detalle(Request $request, int $programacion)
    {
        $p = $this->programacion($programacion, $request->user());

        return ['data' => $this->resumen($p) + [
            'zona' => $p->zona->name,
            'vertices' => $p->zona->vertices(),
            'sectores' => $p->zona->sectores->pluck('name')->all(),
            'foto' => $p->zona->urlFoto(),
            'vehiculo' => $p->vehiculo?->name,
            'placa' => config('rsu.demostracion') ? $p->vehiculo?->plate : null,
            'conductor' => config('rsu.demostracion') ? $p->conductor?->nombre_completo : null,
            'ayudantes' => config('rsu.demostracion') ? $p->ayudantes->pluck('nombre_completo') : [],
            'recorrido' => $p->recorrido ? $this->viaje($p->recorrido, $request->user()) : null,
        ]];
    }

    public function historial(Request $request)
    {
        $usuario = $request->user();
        $pagina = Recorrido::with(['programacion.zona', 'programacion.turno', 'programacion.recorrido'])
            ->where('status', Recorrido::COMPLETADO)
            ->whereHas('programacion', function ($query) use ($usuario) {
                $query->where(function ($query) use ($usuario) {
                    $query->where('zone_id', $usuario->zone_id)->orWhereExists(
                        fn ($periodo) => $periodo->from('citizen_zone_periods')->where('citizen_id', $usuario->id)
                            ->whereColumn('zone_id', 'programmings.zone_id')
                            ->whereColumn('started_at', '<=', 'trips.ended_at')
                            ->whereColumn('ended_at', '>', 'trips.ended_at')
                    );
                });
            })
            ->orderByDesc('ended_at')->orderByDesc('id')->paginate(20);

        return $pagina->through(fn (Recorrido $r) => $this->resumen($r->programacion) + $this->valoraciones($r));
    }

    public function calificar(Request $request, int $programacion)
    {
        $datos = $request->validate(['stars' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:500']);
        $usuario = $request->user();
        $p = $this->programacion($programacion, $usuario);
        $r = $p->recorrido;
        abort_unless($r?->status === Recorrido::COMPLETADO, 409, 'El recorrido todavía no ha finalizado.');

        return DB::transaction(function () use ($datos, $usuario, $r) {
            Recorrido::lockForUpdate()->findOrFail($r->id);
            $filtro = ['trip_id' => $r->id, 'citizen_id' => $usuario->id];
            $actual = DB::table('trip_ratings')->where($filtro)->first();
            if ($actual) {
                abort_unless((int) $actual->stars === (int) $datos['stars'] && $actual->comment === ($datos['comment'] ?? null), 409, 'Ya calificaste este recorrido.');
            } else {
                DB::table('trip_ratings')->insert($filtro + $datos + ['created_at' => now(), 'updated_at' => now()]);
            }

            return $this->valoraciones($r);
        }, 3);
    }

    private function zonaActiva(): array
    {
        return ['required', 'integer', Rule::exists('zones', 'id')->where('status', true)];
    }

    private function abrirPeriodo(Citizen $usuario): void
    {
        DB::table('citizen_zone_periods')->insert(['citizen_id' => $usuario->id, 'zone_id' => $usuario->zone_id, 'started_at' => now()]);
    }

    private function sesion(Citizen $usuario): array
    {
        return ['ciudadano' => $usuario, 'token' => $usuario->createToken('rsu-ciudadano', ['ciudadano'], now()->addDay())->plainTextToken];
    }

    private function formatoZona(Zona $zona): array
    {
        return $zona->only(['id', 'name', 'district', 'description']) + [
            'vertices' => $zona->vertices(),
            'sectores' => $zona->sectores->pluck('name')->all(),
            'foto' => $zona->urlFoto(),
        ];
    }

    private function programacion(int $id, Citizen $usuario): Programacion
    {
        $p = Programacion::with(['zona.coordenadas', 'zona.sectores', 'turno', 'vehiculo', 'conductor', 'ayudantes', 'recorrido'])->findOrFail($id);
        $fin = $p->recorrido?->ended_at;
        $autorizada = (int) $p->zone_id === (int) $usuario->zone_id
            || ($fin && DB::table('citizen_zone_periods')->where('citizen_id', $usuario->id)
                ->where('zone_id', $p->zone_id)->where('started_at', '<=', $fin)->where('ended_at', '>', $fin)->exists());
        abort_unless($autorizada, 404);

        return $p;
    }

    private function resumen(Programacion $p): array
    {
        return [
            'id' => $p->id, 'fecha' => $p->date->toDateString(), 'zona' => $p->zona->name,
            'turno' => $p->turno->name, 'inicio' => $p->inicio()->toIso8601String(), 'fin' => $p->fin()->toIso8601String(),
            'estado' => $p->recorrido?->status ?? ($p->esProgramado() && ! $p->haTerminado() ? $p->status : 'sin_confirmacion'),
        ];
    }

    private function valoraciones(Recorrido $r): array
    {
        $totales = DB::table('trip_ratings')->where('trip_id', $r->id)->selectRaw('COUNT(*) AS votos, AVG(stars) AS promedio')->first();

        return ['votos' => (int) $totales->votos, 'promedio' => $totales->promedio === null ? null : round((float) $totales->promedio, 2)];
    }

    private function viaje(Recorrido $r, Citizen $usuario): array
    {
        $puntos = config('rsu.demostracion') ? $r->puntos()->get()->map(fn ($p) => [
            'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude, 'momento' => $p->recorded_at->toIso8601String(),
        ]) : collect();

        return app(ServicioConductor::class)->recorridoBreve($r) + $this->valoraciones($r) + [
            'puntos' => $puntos, 'ultima_ubicacion' => $puntos->last()['momento'] ?? null,
            'valoracion_propia' => DB::table('trip_ratings')->where('trip_id', $r->id)->where('citizen_id', $usuario->id)->first(['stars', 'comment']),
        ];
    }
}
