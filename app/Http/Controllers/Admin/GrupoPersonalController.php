<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuscaEnCatalogo;
use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\GrupoPersonal;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\Vacacion;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Support\Requisitos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Grupos de personal: plantillas (turno, zona, vehículo, días, conductor y ayudantes) para programar con solo indicar las fechas. */
class GrupoPersonalController extends Controller
{
    use BuscaEnCatalogo;
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.grupos.index');
        }

        $grupos = GrupoPersonal::with(['turno', 'zona', 'vehiculo', 'conductor', 'ayudantes'])->orderBy('name')->get();
        $contexto = $this->contextoDeAlertas($grupos);
        $filas = $grupos->map(fn (GrupoPersonal $g) => $this->fila($g, $contexto));

        return response()->json(['data' => $filas]);
    }

    /** Buscador del formulario de programación: solo grupos activos. */
    public function buscar(Request $request)
    {
        return $this->respuestaDeBusqueda(
            $request,
            GrupoPersonal::with(['turno', 'zona', 'vehiculo'])->where('status', true)->orderBy('name')->get(),
            fn (GrupoPersonal $g) => "{$g->name} {$g->zona?->name} {$g->turno?->name}",
            fn (GrupoPersonal $g) => [
                'id' => $g->id,
                'titulo' => $g->name,
                'detalle' => collect([$g->zona?->name, $g->turno?->name, $g->vehiculo?->plate])->filter()->implode(' · '),
                'iniciales' => mb_strtoupper(mb_substr($g->name, 0, 2)),
                'insignia' => null,
            ],
        );
    }

    /** Datos del grupo para precargar la programación: el usuario puede mantenerlos o cambiarlos. */
    public function plantilla(string $id)
    {
        $grupo = GrupoPersonal::with(['turno', 'zona', 'vehiculo', 'conductor.tipo', 'ayudantes.tipo'])->where('status', true)->findOrFail($id);

        return response()->json($this->plantillaDe($grupo));
    }

    public function create()
    {
        return view('admin.grupos.create', ['dias' => GrupoPersonal::DIAS, 'iniciales' => $this->iniciales(null), 'faltan' => Requisitos::paraGrupo()]);
    }

    public function store(Request $request)
    {
        $datos = $this->validado($request);

        $grupo = DB::transaction(function () use ($datos) {
            $grupo = GrupoPersonal::create($this->paraGuardar($datos));
            $this->guardarAyudantes($grupo, $datos['ayudantes']);

            return $grupo;
        }, 3);

        return $this->exito('Grupo de personal registrado', 'admin.grupos.index', ['grupo' => $this->fila($grupo->load(['turno', 'zona', 'vehiculo', 'conductor', 'ayudantes']))]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        $grupo = GrupoPersonal::with(['turno', 'zona', 'vehiculo', 'conductor.tipo', 'ayudantes.tipo'])->findOrFail($id);

        return view('admin.grupos.edit', [
            'grupo' => $grupo,
            'dias' => GrupoPersonal::DIAS,
            'iniciales' => $this->iniciales($grupo),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $grupo = GrupoPersonal::findOrFail($id);
        $datos = $this->validado($request, $grupo);

        $antes = [$grupo->driver_id, $grupo->vehicle_id, $grupo->ayudantes()->pluck('users.id')->map(fn ($i) => (int) $i)->all(), (bool) $grupo->status];

        DB::transaction(function () use ($grupo, $datos) {
            $grupo->update($this->paraGuardar($datos));
            $this->guardarAyudantes($grupo, $datos['ayudantes']);
        }, 3);

        // El grupo es una plantilla: lo ya programado conserva los datos anteriores. Se avisa para que no se asuma lo contrario.
        $despues = [(int) $datos['driver_id'], (int) $datos['vehicle_id'], array_map('intval', $datos['ayudantes']), (bool) $datos['status']];
        $pendientes = $grupo->programaciones()->where('status', 'programado')->where('date', '>=', today()->toDateString())->count();
        $aviso = $pendientes && $antes !== $despues
            ? ['aviso' => "El grupo tiene {$pendientes} programación(es) pendiente(s) que conservan los datos anteriores. Para cambiarlas use «Reemplazar» en Programación."]
            : [];

        return $this->exito('Grupo de personal actualizado', 'admin.grupos.index', ['grupo' => $this->fila($grupo->fresh(['turno', 'zona', 'vehiculo', 'conductor', 'ayudantes']))] + $aviso);
    }

    public function destroy(string $id)
    {
        $grupo = GrupoPersonal::withCount('programaciones')->findOrFail($id);

        $pendientes = $grupo->programaciones()->where('status', 'programado')->where('date', '>=', today()->toDateString())->count();

        // Las programaciones ya generadas se conservan: solo pierden el enlace con la plantilla.
        $grupo->delete();

        return $this->exito('Grupo de personal eliminado', 'admin.grupos.index', $pendientes
            ? ['aviso' => "Se conservaron {$pendientes} programación(es) pendiente(s) del grupo; ya no dependen de una plantilla."] : []);
    }

    private function validado(Request $request, ?GrupoPersonal $actual = null): array
    {
        $request->merge(['ayudantes' => array_values(array_filter((array) $request->input('ayudantes'), 'filled'))]);

        $request->merge(['status' => $request->boolean('status')]);

        $datos = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('staffgroups', 'name')->ignore($actual?->id)],
            'shift_id' => ['required', 'integer', 'exists:schedules,id'],
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'ayudantes' => ['required', 'array', 'min:1'],
            'ayudantes.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'status' => ['boolean'],
        ], [
            'name.required' => 'Escriba el nombre del grupo.',
            'name.unique' => 'Ya existe un grupo con ese nombre.',
            'shift_id.required' => 'Seleccione el turno.',
            'zone_id.required' => 'Seleccione la zona.',
            'vehicle_id.required' => 'Seleccione el vehículo.',
            'weekdays.required' => 'Seleccione al menos un día de trabajo.',
            'weekdays.min' => 'Seleccione al menos un día de trabajo.',
            'driver_id.required' => 'Seleccione al conductor.',
            'ayudantes.required' => 'Seleccione al menos un ayudante.',
            'ayudantes.min' => 'Seleccione al menos un ayudante.',
            'ayudantes.*.required' => 'Seleccione al ayudante.',
            'ayudantes.*.distinct' => 'No repita al mismo ayudante.',
        ]);

        $errores = [];
        $zona = Zona::find($datos['zone_id']);
        $vehiculo = Vehiculo::find($datos['vehicle_id']);

        if ($zona && ! $zona->status && $zona->id !== $actual?->zone_id) {
            $errores['zone_id'] = 'La zona está inactiva.';
        }

        if ($vehiculo && ! $vehiculo->status && $vehiculo->id !== $actual?->vehicle_id) {
            $errores['vehicle_id'] = 'El vehículo está inactivo.';
        }

        if ($vehiculo && 1 + count($datos['ayudantes']) > $vehiculo->occupant_capacity) {
            $errores['ayudantes'] = "El vehículo tiene capacidad para {$vehiculo->occupant_capacity} persona(s): 1 conductor y "
                .max(0, $vehiculo->occupant_capacity - 1).' ayudante(s).';
        }

        $conductor = Personal::find($datos['driver_id']);
        if ($conductor && $conductor->usertype_id !== TipoPersonal::conductor()?->id) {
            $errores['driver_id'] = 'La persona elegida no es conductor.';
        } elseif ($conductor && ! $conductor->status) {
            $errores['driver_id'] = 'El conductor está inactivo.';
        }

        if (in_array($datos['driver_id'], array_map('intval', $datos['ayudantes']), true)) {
            $errores['ayudantes'] = 'El conductor no puede ser también ayudante.';
        }

        foreach (Personal::whereIn('users.id', $datos['ayudantes'])->get() as $ayudante) {
            if ($ayudante->usertype_id !== TipoPersonal::ayudante()?->id) {
                $errores['ayudantes'] = "{$ayudante->nombre_completo} no es ayudante.";
            } elseif (! $ayudante->status) {
                $errores['ayudantes'] = "{$ayudante->nombre_completo} está inactivo.";
            }
        }

        // Dos grupos activos del mismo turno y con días en común no pueden compartir conductor, vehículo ni ayudantes.
        if ($datos['status']) {
            $dias = array_map('intval', $datos['weekdays']);
            $ayudantes = array_map('intval', $datos['ayudantes']);

            foreach (GrupoPersonal::with('ayudantes')->where('status', true)->where('shift_id', $datos['shift_id'])
                ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))->get() as $otro) {
                $comunes = array_values(array_intersect($otro->dias_ordenados, $dias));

                if (! $comunes) {
                    continue;
                }

                $textoDias = collect($comunes)->map(fn ($d) => GrupoPersonal::DIAS[$d])->implode(', ');

                if ($otro->driver_id === (int) $datos['driver_id']) {
                    $errores['driver_id'] = "El conductor ya está en el grupo «{$otro->name}», del mismo turno y con días en común ({$textoDias}).";
                }

                if ($otro->vehicle_id === (int) $datos['vehicle_id']) {
                    $errores['vehicle_id'] = "El vehículo ya está en el grupo «{$otro->name}», del mismo turno y con días en común ({$textoDias}).";
                }

                if (array_intersect($otro->ayudantes->pluck('id')->map(fn ($i) => (int) $i)->all(), $ayudantes)) {
                    $errores['ayudantes'] = "Un ayudante ya está en el grupo «{$otro->name}», del mismo turno y con días en común ({$textoDias}).";
                }
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return $datos;
    }

    private function paraGuardar(array $datos): array
    {
        $dias = array_values(array_unique(array_map('intval', $datos['weekdays'])));
        sort($dias);

        return [
            'name' => trim($datos['name']),
            'shift_id' => $datos['shift_id'],
            'zone_id' => $datos['zone_id'],
            'vehicle_id' => $datos['vehicle_id'],
            'weekdays' => $dias,
            'driver_id' => $datos['driver_id'],
            'status' => $datos['status'],
        ];
    }

    private function guardarAyudantes(GrupoPersonal $grupo, array $ids): void
    {
        $grupo->ayudantes()->detach();

        foreach (array_values($ids) as $posicion => $id) {
            $grupo->ayudantes()->attach($id, ['position' => $posicion + 1]);
        }
    }

    /** @return array<string, mixed> */
    private function plantillaDe(GrupoPersonal $grupo): array
    {
        return [
            'id' => $grupo->id,
            'nombre' => $grupo->name,
            'dias' => $grupo->dias_ordenados,
            'turno' => TurnoController::itemDeBuscador($grupo->turno),
            'zona' => ZonaController::itemDeBuscador($grupo->zona),
            'vehiculo' => VehiculoController::itemDeBuscador($grupo->vehiculo),
            'conductor' => self::itemDePersona($grupo->conductor),
            'ayudantes' => $grupo->ayudantes->map(fn (Personal $p) => self::itemDePersona($p))->all(),
        ];
    }

    /** Forma que espera Tads.buscador (también para precargar el valor elegido). */
    public static function itemDePersona(Personal $persona): array
    {
        return [
            'id' => $persona->id,
            'titulo' => $persona->nombre_completo,
            'detalle' => "DNI {$persona->dni} · ".($persona->tipo?->name ?? 'Sin función'),
            'foto' => $persona->url_foto,
            'iniciales' => mb_strtoupper(mb_substr($persona->name, 0, 1).mb_substr((string) $persona->last_name, 0, 1)),
            'insignia' => null,
        ];
    }

    /** Valores ya elegidos (al editar) para los buscadores del formulario. */
    private function iniciales(?GrupoPersonal $grupo): array
    {
        if (! $grupo) {
            return ['turno' => null, 'zona' => null, 'vehiculo' => null, 'conductor' => null, 'ayudantes' => []];
        }

        $plantilla = $this->plantillaDe($grupo);

        return array_intersect_key($plantilla, array_flip(['turno', 'zona', 'vehiculo', 'conductor', 'ayudantes']));
    }

    /**
     * Quién de los grupos hoy no podría trabajar: sin contrato vigente o de vacaciones (dos consultas para todos).
     *
     * @return array{sinContrato: array<int, true>, vacaciones: array<int, true>}
     */
    private function contextoDeAlertas($grupos): array
    {
        $ids = $grupos->flatMap(fn (GrupoPersonal $g) => [$g->driver_id, ...$g->ayudantes->pluck('id')->all()])->unique()->values();

        $conContrato = Contrato::vigente()->whereIn('user_id', $ids)->pluck('user_id')->flip();
        $deVacaciones = Vacacion::where('status', Vacacion::APROBADA)->queSeCruzaCon(today(), today())->whereIn('user_id', $ids)->pluck('user_id')->flip();

        return [
            'sinContrato' => $ids->reject(fn ($i) => $conContrato->has($i))->flip()->map(fn () => true)->all(),
            'vacaciones' => $deVacaciones->map(fn () => true)->all(),
        ];
    }

    /** @return list<string> */
    private function alertasDe(GrupoPersonal $grupo, array $contexto): array
    {
        $alertas = [];

        foreach ([$grupo->conductor, ...$grupo->ayudantes->all()] as $persona) {
            if (! $persona) {
                continue;
            }

            if (! $persona->status) {
                $alertas[] = "{$persona->nombre_completo} está inactivo";
            } elseif (isset($contexto['sinContrato'][$persona->id])) {
                $alertas[] = "{$persona->nombre_completo} no tiene contrato vigente";
            } elseif (isset($contexto['vacaciones'][$persona->id])) {
                $alertas[] = "{$persona->nombre_completo} está de vacaciones";
            }
        }

        if ($grupo->vehiculo && ! $grupo->vehiculo->status) {
            $alertas[] = "El vehículo {$grupo->vehiculo->plate} está inactivo";
        }

        if ($grupo->zona && ! $grupo->zona->status) {
            $alertas[] = "La zona {$grupo->zona->name} está inactiva";
        }

        return $alertas;
    }

    private function fila(GrupoPersonal $grupo, ?array $contexto = null): array
    {
        $contexto ??= $this->contextoDeAlertas(collect([$grupo]));

        return [
            'alertas' => $this->alertasDe($grupo, $contexto),
            'id' => $grupo->id,
            'nombre' => $grupo->name,
            'turno' => $grupo->turno?->name,
            'zona' => $grupo->zona?->name,
            'vehiculo' => $grupo->vehiculo?->plate,
            'conductor' => $grupo->conductor?->nombre_completo,
            'ayudantes' => $grupo->ayudantes->map(fn (Personal $p) => $p->nombre_completo)->all(),
            'dias' => $grupo->dias_ordenados,
            'activo' => (bool) $grupo->status,
            'creado' => $grupo->created_at?->format('d/m/Y H:i'),
        ];
    }
}
