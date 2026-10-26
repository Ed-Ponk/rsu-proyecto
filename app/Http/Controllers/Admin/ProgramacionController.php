<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Bitacora;
use App\Models\Feriado;
use App\Models\GrupoPersonal;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\Turno;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Services\ReglasProgramacion;
use App\Support\Requisitos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Programaciones de rutas: una fila por día de trabajo. Se generan desde un grupo de personal (individual)
 * o desde varios grupos a la vez (masiva); en ambos casos se debe validar la disponibilidad antes de guardar.
 */
class ProgramacionController extends Controller
{
    use RespondeAjax;

    /** Filas máximas que devuelve el listado de una consulta. */
    private const TOPE_LISTADO = 2000;

    public function __construct(private readonly ReglasProgramacion $reglas) {}

    public function index(Request $request)
    {
        if (! $request->ajax()) {
            return view('admin.programaciones.index', [
                'desde' => today()->startOfMonth()->toDateString(),
                'hasta' => today()->endOfMonth()->toDateString(),
            ]);
        }

        $filtro = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'zona_id' => ['nullable', 'integer'],
            'turno_id' => ['nullable', 'integer'],
        ], ['desde.date' => 'La fecha inicial no es válida.', 'hasta.date' => 'La fecha final no es válida.']);

        $desde = Carbon::parse($filtro['desde'] ?? today()->startOfMonth());
        $hasta = Carbon::parse($filtro['hasta'] ?? today()->endOfMonth());

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $filas = Programacion::with(['zona', 'turno', 'vehiculo', 'conductor', 'ayudantes'])
            ->entre($desde->toDateString(), $hasta->toDateString())
            ->when($filtro['zona_id'] ?? null, fn ($q, $v) => $q->where('zone_id', $v))
            ->when($filtro['turno_id'] ?? null, fn ($q, $v) => $q->where('shift_id', $v))
            ->orderBy('date')->orderBy('shift_id')->orderBy('id')
            ->limit(self::TOPE_LISTADO)->get();

        $contexto = $this->contextoDeLista($filas);
        $filas = $filas->map(fn (Programacion $p) => $this->fila($p, $contexto));

        return response()->json(['data' => $filas, 'truncado' => $filas->count() >= self::TOPE_LISTADO]);
    }

    /* ---------------------------- programación individual ---------------------------- */

    public function create()
    {
        return view('admin.programaciones.create', ['dias' => GrupoPersonal::DIAS, 'faltan' => Requisitos::paraProgramacion()]);
    }

    /** Revisa la disponibilidad sin guardar nada (botón "Validar disponibilidad"). */
    public function validar(Request $request)
    {
        $datos = $this->validadoIndividual($request);
        $solicitud = $this->solicitudDeGrupo($datos, $this->grupoActivo($datos['grupo_id']));

        return response()->json($this->respuestaDeRevision($this->reglas->revisar([$solicitud])));
    }

    public function store(Request $request)
    {
        $datos = $this->validadoIndividual($request);
        $grupo = $this->grupoActivo($datos['grupo_id']);

        $creadas = $this->reglas->generar([$this->solicitudDeGrupo($datos, $grupo)], auth()->id());

        return $this->exito($this->textoDeCreadas($creadas), 'admin.programaciones.index');
    }

    /* ---------------------------- programación masiva ---------------------------- */

    public function masiva()
    {
        $grupos = GrupoPersonal::with(['turno', 'zona', 'vehiculo', 'conductor'])->where('status', true)->orderBy('name')->get();

        return view('admin.programaciones.masiva', ['grupos' => $grupos]);
    }

    public function validarMasiva(Request $request)
    {
        return response()->json($this->respuestaDeRevision($this->reglas->revisar($this->solicitudesMasivas($request))));
    }

    public function guardarMasiva(Request $request)
    {
        $creadas = $this->reglas->generar($this->solicitudesMasivas($request), auth()->id());

        return $this->exito($this->textoDeCreadas($creadas), 'admin.programaciones.index');
    }

    /* ---------------------------- reemplazar persona o vehículo ---------------------------- */

    public function reemplazo()
    {
        return view('admin.programaciones.reemplazo', ['desde' => today()->toDateString(), 'hasta' => today()->addDays(30)->toDateString()]);
    }

    public function validarReemplazo(Request $request)
    {
        $revision = $this->reglas->revisarReemplazo($this->validadoReemplazo($request));

        return response()->json([
            'ok' => $revision['ok'], 'errores' => $revision['errores'], 'sugerencias' => $revision['sugerencias'],
            'avisos' => $revision['avisos'], 'total' => $revision['total'],
        ]);
    }

    public function guardarReemplazo(Request $request)
    {
        $total = $this->reglas->reemplazar($this->validadoReemplazo($request));

        return $this->exito($total === 1 ? 'Se reemplazó en 1 programación' : "Se reemplazó en {$total} programaciones", 'admin.programaciones.index');
    }

    private function validadoReemplazo(Request $request): array
    {
        $rol = $request->input('rol');

        $datos = $request->validate([
            'rol' => ['required', 'in:conductor,ayudante,vehiculo'],
            'saliente_id' => ['required', 'integer', 'exists:'.($rol === 'vehiculo' ? 'vehicles' : 'users').',id'],
            'entrante_id' => ['required', 'integer', 'exists:'.($rol === 'vehiculo' ? 'vehicles' : 'users').',id'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ], [
            'rol.required' => 'Elija qué desea reemplazar.',
            'saliente_id.required' => 'Seleccione a quién o qué sale.',
            'entrante_id.required' => 'Seleccione a quién o qué entra.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        if (Carbon::parse($datos['desde'])->diffInDays(Carbon::parse($datos['hasta'])) + 1 > ReglasProgramacion::MAX_DIAS_RANGO) {
            throw ValidationException::withMessages(['hasta' => 'El rango no puede superar '.ReglasProgramacion::MAX_DIAS_RANGO.' días.']);
        }

        return $datos;
    }

    /** Quita las programaciones pendientes que caen en un feriado activo (por ejemplo, un feriado decretado a último momento). */
    public function quitarEnFeriados(Request $request)
    {
        $datos = $request->validate(['desde' => ['required', 'date'], 'hasta' => ['required', 'date', 'after_or_equal:desde']]);

        $filas = Programacion::where('status', Programacion::PROGRAMADO)->enFeriado()
            ->where('date', '>=', max($datos['desde'], today()->toDateString()))->where('date', '<=', $datos['hasta'])
            ->limit(self::TOPE_LISTADO)->get();

        $filas->each->delete();

        return $this->exito($filas->count() === 1 ? 'Se quitó 1 programación en feriado' : "Se quitaron {$filas->count()} programaciones en feriados", 'admin.programaciones.index');
    }

    /* ---------------------------- modificar / eliminar / finalizar ---------------------------- */

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        $programacion = Programacion::with(['zona', 'turno', 'vehiculo', 'conductor.tipo', 'ayudantes.tipo'])->findOrFail($id);

        if (! $programacion->esProgramado()) {
            return $this->rechazo('Solo las programaciones en estado «Programado» se pueden modificar.', 'admin.programaciones.index');
        }

        if ($programacion->date->lt(today())) {
            return $this->rechazo('Esta programación ya pasó su fecha: solo se puede finalizar o eliminar.', 'admin.programaciones.index');
        }

        return view('admin.programaciones.edit', [
            'programacion' => $programacion,
            'vehiculoItem' => VehiculoController::itemDeBuscador($programacion->vehiculo),
            'conductorItem' => GrupoPersonalController::itemDePersona($programacion->conductor),
            'ayudantesItems' => $programacion->ayudantes->map(fn (Personal $p) => GrupoPersonalController::itemDePersona($p))->all(),
        ]);
    }

    /** Revisa la disponibilidad de una programación que se está modificando. */
    public function validarCambio(Request $request, string $id)
    {
        $programacion = Programacion::findOrFail($id);
        $datos = $this->validadoCambio($request);

        $revision = $this->reglas->revisar([$this->solicitudDeCambio($programacion, $datos)]);

        return response()->json($this->respuestaDeRevision($revision));
    }

    public function update(Request $request, string $id)
    {
        $programacion = Programacion::findOrFail($id);

        if (! $programacion->esProgramado()) {
            return $this->rechazo('Solo las programaciones en estado «Programado» se pueden modificar.', 'admin.programaciones.index');
        }

        $this->reglas->actualizar($programacion, $this->validadoCambio($request));

        return $this->exito('Programación actualizada', 'admin.programaciones.index');
    }

    public function destroy(string $id)
    {
        $programacion = Programacion::findOrFail($id);

        if (! $programacion->esProgramado()) {
            return $this->rechazo('Una programación finalizada no se puede eliminar.', 'admin.programaciones.index');
        }

        // Si alguien ya marcó asistencia, la programación es el respaldo de ese trabajo: no se borra, se finaliza.
        $trabajaron = $programacion->personasConAsistencia();
        if ($trabajaron->isNotEmpty()) {
            return $this->rechazo('No se puede eliminar: '.$trabajaron->map->nombre_completo->implode(', ').' ya registró asistencia ese día. Finalice la programación en su lugar.', 'admin.programaciones.index');
        }

        $programacion->delete();

        return $this->exito('Programación eliminada', 'admin.programaciones.index');
    }

    /** Finalizar: el día de trabajo ya se cumplió. Solo desde el día programado en adelante. */
    public function finalizar(string $id)
    {
        $programacion = Programacion::findOrFail($id);

        if (! $programacion->esProgramado()) {
            return $this->rechazo('Esta programación ya está finalizada.', 'admin.programaciones.index');
        }

        if ($programacion->date->gt(today())) {
            return $this->rechazo('No se puede finalizar una programación que todavía no llegó a su fecha ('.$programacion->date->format('d/m/Y').').', 'admin.programaciones.index');
        }

        // Hoy: no se da por cumplido un turno que todavía no empezó.
        if ($programacion->date->isToday() && now()->hour * 60 + now()->minute < $programacion->minutoDeInicio()) {
            $hora = substr($programacion->turno->time_start, 0, 5);

            return $this->rechazo("El turno empieza a las {$hora}: todavía no se puede finalizar.", 'admin.programaciones.index');
        }

        $programacion->update(['status' => Programacion::FINALIZADO]);

        return $this->exito('Programación finalizada', 'admin.programaciones.index');
    }

    /** Detalle y historial de cambios (ventana emergente). */
    public function historial(string $id)
    {
        $programacion = Programacion::with(['grupo', 'zona', 'turno', 'vehiculo', 'conductor', 'ayudantes'])->findOrFail($id);

        $cambios = Bitacora::with('usuario')
            ->where('model', 'Programacion')->where('model_id', $programacion->id)
            ->orderByDesc('id')->get()
            ->map(fn (Bitacora $b) => [
                'fecha' => $b->created_at?->format('d/m/Y H:i'),
                'usuario' => $b->usuario?->name ?? 'Sistema',
                'accion' => $b->action,
                'detalle' => $this->describirCambio($b),
            ]);

        return view('admin.programaciones.historial', ['programacion' => $programacion, 'cambios' => $cambios]);
    }

    /* ---------------------------- ayudas ---------------------------- */

    private function grupoActivo(int $id): GrupoPersonal
    {
        $grupo = GrupoPersonal::where('status', true)->find($id);

        if (! $grupo) {
            throw ValidationException::withMessages(['grupo_id' => 'El grupo de personal está inactivo o ya no existe.']);
        }

        return $grupo;
    }

    private function validadoIndividual(Request $request): array
    {
        $request->merge(['ayudantes' => array_values(array_filter((array) $request->input('ayudantes'), 'filled'))]);

        return $request->validate([
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:inicio'],
            'grupo_id' => ['required', 'integer', 'exists:staffgroups,id'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7'],
            'conductor_id' => ['required', 'integer', 'exists:users,id'],
            'ayudantes' => ['required', 'array', 'min:1'],
            'ayudantes.*' => ['required', 'integer', 'exists:users,id'],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [
            'inicio.required' => 'Indique la fecha de inicio.',
            'fin.required' => 'Indique la fecha de fin.',
            'fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'grupo_id.required' => 'Seleccione un grupo de personal.',
            'dias.required' => 'Seleccione al menos un día de trabajo.',
            'dias.min' => 'Seleccione al menos un día de trabajo.',
            'conductor_id.required' => 'Seleccione al conductor.',
            'ayudantes.required' => 'Seleccione al menos un ayudante.',
            'ayudantes.min' => 'Seleccione al menos un ayudante.',
            'ayudantes.*.required' => 'Seleccione al ayudante.',
        ]);
    }

    private function validadoCambio(Request $request): array
    {
        $request->merge(['ayudantes' => array_values(array_filter((array) $request->input('ayudantes'), 'filled'))]);

        return $request->validate([
            'vehiculo_id' => ['required', 'integer', 'exists:vehicles,id'],
            'conductor_id' => ['required', 'integer', 'exists:users,id'],
            'ayudantes' => ['required', 'array', 'min:1'],
            'ayudantes.*' => ['required', 'integer', 'exists:users,id'],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [
            'vehiculo_id.required' => 'Seleccione el vehículo.',
            'conductor_id.required' => 'Seleccione al conductor.',
            'ayudantes.required' => 'Seleccione al menos un ayudante.',
            'ayudantes.min' => 'Seleccione al menos un ayudante.',
            'ayudantes.*.required' => 'Seleccione al ayudante.',
        ]);
    }

    /** Fechas y días vienen del formulario; turno, zona y vehículo, del grupo. */
    private function solicitudDeGrupo(array $datos, GrupoPersonal $grupo): array
    {
        return [
            'inicio' => $datos['inicio'],
            'fin' => $datos['fin'],
            'dias' => $datos['dias'] ?? $grupo->dias_ordenados,
            'turno_id' => $grupo->shift_id,
            'zona_id' => $grupo->zone_id,
            'vehiculo_id' => $grupo->vehicle_id,
            'conductor_id' => $datos['conductor_id'] ?? $grupo->driver_id,
            'ayudantes' => $datos['ayudantes'] ?? $grupo->ayudantes()->pluck('users.id')->all(),
            'grupo_id' => $grupo->id,
            'etiqueta' => $grupo->name,
            'notas' => $datos['notas'] ?? null,
        ];
    }

    private function solicitudDeCambio(Programacion $programacion, array $datos): array
    {
        return [
            'inicio' => $programacion->date->toDateString(),
            'fin' => $programacion->date->toDateString(),
            'dias' => [$programacion->date->dayOfWeekIso],
            'turno_id' => $programacion->shift_id,
            'zona_id' => $programacion->zone_id,
            'vehiculo_id' => $datos['vehiculo_id'],
            'conductor_id' => $datos['conductor_id'],
            'ayudantes' => $datos['ayudantes'],
            'ignorar_id' => $programacion->id,
        ];
    }

    /** Programación masiva: cada grupo elegido se genera tal como está definido, con el mismo rango de fechas. */
    private function solicitudesMasivas(Request $request): array
    {
        $datos = $request->validate([
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:inicio'],
            'grupos' => ['required', 'array', 'min:1'],
            'grupos.*' => ['integer', 'distinct', 'exists:staffgroups,id'],
        ], [
            'inicio.required' => 'Indique la fecha de inicio.',
            'fin.required' => 'Indique la fecha de fin.',
            'fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'grupos.required' => 'Seleccione al menos un grupo de personal.',
            'grupos.min' => 'Seleccione al menos un grupo de personal.',
        ]);

        $grupos = GrupoPersonal::with('ayudantes')->whereIn('id', $datos['grupos'])->orderBy('name')->get();

        // Un grupo inactivo no se salta en silencio: se avisa para que no se programe menos de lo esperado.
        $inactivos = $grupos->where('status', false)->pluck('name');
        if ($inactivos->isNotEmpty()) {
            throw ValidationException::withMessages(['grupos' => 'Grupo(s) inactivo(s): '.$inactivos->implode(', ').'. Quítelos de la selección o actívelos.']);
        }

        return $grupos->map(fn (GrupoPersonal $g) => $this->solicitudDeGrupo(
            ['inicio' => $datos['inicio'], 'fin' => $datos['fin']],
            $g,
        ))->all();
    }

    private function respuestaDeRevision(array $revision): array
    {
        $total = array_sum(array_map(fn (array $p) => count($p['fechas']), $revision['plan']));

        return [
            'ok' => $revision['ok'],
            'errores' => $revision['errores'],
            'sugerencias' => $revision['sugerencias'],
            'avisos' => $revision['avisos'],
            'total' => $total,
        ];
    }

    private function textoDeCreadas(int $cantidad): string
    {
        return $cantidad === 1 ? 'Se generó 1 programación' : "Se generaron {$cantidad} programaciones";
    }

    /**
     * Datos extra para marcar el listado: los feriados del rango y quién ya registró asistencia (una sola consulta cada uno).
     *
     * @return array{feriados: array<string, string>, presentes: array<string, bool>}
     */
    private function contextoDeLista($filas): array
    {
        if ($filas->isEmpty()) {
            return ['feriados' => [], 'presentes' => []];
        }

        $fechas = $filas->map(fn (Programacion $p) => $p->date->toDateString());

        $feriados = Feriado::activo()->entre($fechas->min(), $fechas->max())->get()
            ->mapWithKeys(fn (Feriado $f) => [$f->date->toDateString() => $f->description])->all();

        $presentes = Asistencia::where('status', Asistencia::PRESENTE)
            ->where('shift_date', '>=', $fechas->min())->where('shift_date', '<=', min($fechas->max(), today()->toDateString()))
            ->get(['user_id', 'shift_date'])
            ->mapWithKeys(fn (Asistencia $a) => [$a->user_id.'|'.$a->shift_date->toDateString() => true])->all();

        return ['feriados' => $feriados, 'presentes' => $presentes];
    }

    /** Quién de la programación todavía no registró asistencia en un día que ya empezó (solo informativo). @return list<string> */
    private function sinAsistencia(Programacion $p, array $presentes): array
    {
        if (! $p->esProgramado()) {
            return [];
        }

        $hoy = today();
        $inicio = ((int) substr($p->turno?->time_start ?? '00:00', 0, 2)) * 60 + (int) substr($p->turno?->time_start ?? '00:00', 3, 2);

        // Hoy se espera una hora después del inicio del turno para no marcar a quien aún no llegó.
        $yaEmpezo = $p->date->lt($hoy) || ($p->date->eq($hoy) && now()->hour * 60 + now()->minute >= $inicio + 60);

        if (! $yaEmpezo) {
            return [];
        }

        $dia = $p->date->toDateString();

        return collect([$p->conductor, ...$p->ayudantes->all()])->filter()
            ->reject(fn (Personal $x) => isset($presentes[$x->id.'|'.$dia]))
            ->map(fn (Personal $x) => $x->nombre_completo)->values()->all();
    }

    private function fila(Programacion $p, array $contexto = ['feriados' => [], 'presentes' => []]): array
    {
        return [
            'feriado' => $contexto['feriados'][$p->date->toDateString()] ?? null,
            'sin_asistencia' => $this->sinAsistencia($p, $contexto['presentes']),
            'id' => $p->id,
            'fecha' => $p->date->format('d/m/Y'),
            'estado' => $p->status,
            'estado_texto' => Programacion::ESTADOS[$p->status] ?? $p->status,
            'zona' => $p->zona?->name,
            'turno' => $p->turno?->name,
            'vehiculo' => $p->vehiculo?->name,
            'placa' => $p->vehiculo?->plate,
            'conductor' => $p->conductor?->nombre_completo,
            'ayudantes' => $p->ayudantes->map(fn (Personal $a) => $a->nombre_completo)->all(),
            'notas' => $p->notes,
            'puede_finalizar' => $p->esProgramado() && $p->date->lte(today()),
            'puede_modificar' => $p->esProgramado() && $p->date->gte(today()),
        ];
    }

    /** Resumen legible de un movimiento de la bitácora. */
    private function describirCambio(Bitacora $b): string
    {
        if ($b->action === 'creado') {
            return 'Programación creada';
        }

        if ($b->action === 'eliminado') {
            return 'Programación eliminada';
        }

        $etiquetas = ['status' => 'Estado', 'driver_id' => 'Conductor', 'vehicle_id' => 'Vehículo', 'notes' => 'Observaciones', 'ayudantes' => 'Ayudantes', 'date' => 'Fecha'];
        $partes = [];

        foreach ($b->after ?? [] as $campo => $nuevo) {
            $antes = $b->before[$campo] ?? null;
            $partes[] = ($etiquetas[$campo] ?? $campo).': '.$this->valorLegible($campo, $antes).' → '.$this->valorLegible($campo, $nuevo);
        }

        return implode('; ', $partes) ?: 'Modificada';
    }

    private function valorLegible(string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($campo) {
            'driver_id' => Personal::find($valor)?->nombre_completo ?? "#{$valor}",
            'vehicle_id' => ($v = Vehiculo::find($valor)) ? "{$v->name} ({$v->plate})" : "#{$valor}",
            'status' => Programacion::ESTADOS[$valor] ?? (string) $valor,
            default => (string) $valor,
        };
    }
}
