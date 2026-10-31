<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Bitacora;
use App\Models\Cambio;
use App\Models\Feriado;
use App\Models\GrupoPersonal;
use App\Models\Motivo;
use App\Models\Personal;
use App\Models\Programacion;
use App\Models\TipoPersonal;
use App\Models\Turno;
use App\Models\Vehiculo;
use App\Models\Zona;
use App\Services\MonitorDeProgramacion;
use App\Services\ReglasProgramacion;
use App\Services\ServicioCambios;
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

    public function __construct(private readonly ReglasProgramacion $reglas, private readonly ServicioCambios $cambios) {}

    public function index(Request $request)
    {
        // Por defecto se ve el mes actual y los dos siguientes: lo que se programa suele ser a futuro.
        $porDefectoDesde = today()->startOfMonth();
        $porDefectoHasta = today()->addMonthsNoOverflow(2)->endOfMonth();

        if (! $request->ajax()) {
            return view('admin.programaciones.index', [
                'desde' => $porDefectoDesde->toDateString(),
                'hasta' => $porDefectoHasta->toDateString(),
                'grupos' => GrupoPersonal::orderBy('name')->get(['id', 'name']),
                'personas' => Personal::activos()->whereNotNull('users.usertype_id')->orderBy('users.name')->orderBy('users.last_name')->get(),
            ]);
        }

        $filtro = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'zona_id' => ['nullable', 'integer'],
            'turno_id' => ['nullable', 'integer'],
            // "sin" = programaciones que ya no tienen grupo (se eliminó su plantilla).
            'grupo_id' => ['nullable', 'regex:/^(\d+|sin)$/'],
            'estado' => ['nullable', 'in:'.implode(',', array_keys(Programacion::ESTADOS))],
            'persona_id' => ['nullable', 'integer'],
            'vista' => ['nullable', 'in:detalle,resumen'],
        ], ['desde.date' => 'La fecha inicial no es válida.', 'hasta.date' => 'La fecha final no es válida.']);

        $desde = Carbon::parse($filtro['desde'] ?? $porDefectoDesde);
        $hasta = Carbon::parse($filtro['hasta'] ?? $porDefectoHasta);

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $consulta = Programacion::entre($desde->toDateString(), $hasta->toDateString())
            ->when($filtro['zona_id'] ?? null, fn ($q, $v) => $q->where('zone_id', $v))
            ->when($filtro['turno_id'] ?? null, fn ($q, $v) => $q->where('shift_id', $v))
            ->when($filtro['grupo_id'] ?? null, fn ($q, $v) => $v === 'sin' ? $q->whereNull('group_id') : $q->where('group_id', $v))
            ->when($filtro['estado'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filtro['persona_id'] ?? null, fn ($q, $v) => $q->dePersona((int) $v));

        if (($filtro['vista'] ?? null) === 'resumen') {
            return response()->json(['data' => $this->resumenPorGrupo($consulta)]);
        }

        $filas = $consulta->with(['grupo', 'zona', 'turno', 'vehiculo', 'conductor', 'ayudantes'])
            ->orderBy('date')->orderBy('shift_id')->orderBy('id')
            ->limit(self::TOPE_LISTADO)->get();

        $contexto = $this->contextoDeLista($filas);
        $filas = $filas->map(fn (Programacion $p) => $this->fila($p, $contexto));

        return response()->json(['data' => $filas, 'truncado' => $filas->count() >= self::TOPE_LISTADO]);
    }

    /**
     * Una tarjeta por grupo con lo esencial (rango, cantidades, próximo día). Es la vista de entrada: decenas de
     * filas diarias se leen como 3 o 4 tarjetas, y el detalle día por día se abre solo cuando hace falta.
     */
    private function resumenPorGrupo($consulta): array
    {
        $filas = $consulta->with(['grupo', 'zona', 'turno', 'vehiculo', 'conductor'])->orderBy('date')->get();
        $hoy = today()->toDateString();

        return $filas->groupBy(fn (Programacion $p) => $p->group_id ?? 'sin')->map(function ($dias, $clave) use ($hoy) {
            $primera = $dias->first();
            $pendientes = $dias->filter->esProgramado();
            $proxima = $pendientes->first(fn (Programacion $p) => $p->date->toDateString() >= $hoy);
            // Pendientes de días ya pasados: hay que finalizarlas (o eliminarlas si no se trabajó).
            $atrasadas = $pendientes->filter(fn (Programacion $p) => $p->date->toDateString() < $hoy)->count();
            $lista = fn (string $relacion, string $campo) => $dias->map(fn (Programacion $p) => $p->{$relacion}?->{$campo})->filter()->unique()->values()->all();
            // Con cuántos días: si hubo un reemplazo (vacaciones, taller), se ve que fueron días distintos y no dos a la vez.
            $conDias = fn (string $relacion, string $campo) => $dias->groupBy(fn (Programacion $p) => $p->{$relacion}?->{$campo} ?? '—')
                ->map(fn ($grupo, $nombre) => ['nombre' => $nombre, 'dias' => $grupo->count()])->sortByDesc('dias')->values()->all();

            return [
                'grupo_id' => $clave,
                'grupo' => $primera->grupo?->name ?? 'Sin grupo (plantilla eliminada)',
                'zonas' => $lista('zona', 'name'),
                'turnos' => $lista('turno', 'name'),
                'placas' => $conDias('vehiculo', 'plate'),
                'conductores' => $conDias('conductor', 'nombre_completo'),
                'desde' => $dias->first()->date->format('d/m/Y'),
                'hasta' => $dias->last()->date->format('d/m/Y'),
                'total' => $dias->count(),
                'programadas' => $pendientes->count(),
                'finalizadas' => $dias->count() - $pendientes->count(),
                'atrasadas' => $atrasadas,
                'proxima' => $proxima?->date->format('d/m/Y'),
            ];
        })->sortBy('grupo')->values()->all();
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
        $grupos = GrupoPersonal::with(['turno', 'zona', 'vehiculo', 'conductor', 'ayudantes.tipo'])->where('status', true)->orderBy('name')->get();

        $lista = fn (?TipoPersonal $tipo) => Personal::activos()->where('users.usertype_id', $tipo?->id)->orderBy('users.last_name')->orderBy('users.name')->get()
            ->map(fn (Personal $p) => ['id' => $p->id, 'nombre' => $p->nombre_completo])->values();

        return view('admin.programaciones.masiva', [
            'grupos' => $grupos,
            'turnos' => Turno::orderBy('time_start')->get(['id', 'name']),
            'conductores' => $lista(TipoPersonal::conductor()),
            'ayudantes' => $lista(TipoPersonal::ayudante()),
        ]);
    }

    /** Feriados activos dentro del rango, para que el usuario elija cuáles trabajar. */
    public function feriadosDelRango(Request $request)
    {
        $datos = $request->validate(['inicio' => ['required', 'date'], 'fin' => ['required', 'date', 'after_or_equal:inicio']]);

        $feriados = Feriado::activo()->entre($datos['inicio'], $datos['fin'])->orderBy('date')->get()
            ->map(fn (Feriado $f) => ['fecha' => $f->date->toDateString(), 'texto' => $f->date->format('d/m/Y'), 'dia' => $f->date->translatedFormat('l'), 'descripcion' => $f->description]);

        return response()->json(['data' => $feriados]);
    }

    /** «Detalles del grupo»: su personal y el historial de cambios (individuales y masivos) de sus programaciones. */
    public function detalleDeGrupo(string $grupo)
    {
        $grupo = GrupoPersonal::with(['turno', 'zona', 'vehiculo', 'conductor', 'ayudantes'])->findOrFail($grupo);

        $cambios = Cambio::with(['usuario', 'programacion:id,date'])->where('group_id', $grupo->id)->orderByDesc('id')->limit(200)->get();

        return view('admin.programaciones.grupo', [
            'grupo' => $grupo,
            'cambios' => $cambios,
            'programaciones' => Programacion::where('group_id', $grupo->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
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
        return view('admin.programaciones.reemplazo', ['desde' => today()->toDateString(), 'hasta' => today()->addDays(30)->toDateString(), 'motivos' => Motivo::orderBy('name')->get()]);
    }

    public function validarReemplazo(Request $request)
    {
        $revision = $this->reglas->revisarReemplazo($this->validadoReemplazo($request, false));

        return response()->json([
            'ok' => $revision['ok'], 'errores' => $revision['errores'], 'sugerencias' => $revision['sugerencias'],
            'avisos' => $revision['avisos'], 'total' => $revision['total'], 'problemas' => $revision['problemas'], 'notas' => $revision['notas'],
        ]);
    }

    public function guardarReemplazo(Request $request)
    {
        $total = $this->reglas->reemplazar($this->validadoReemplazo($request) + ['usuario_id' => auth()->id()]);

        return $this->exito($total === 1 ? 'Se reemplazó en 1 programación' : "Se reemplazó en {$total} programaciones", 'admin.programaciones.index');
    }

    private function validadoReemplazo(Request $request, bool $exigirMotivo = true): array
    {
        $rol = $request->input('rol');

        $datos = $request->validate([
            'rol' => ['required', 'in:conductor,ayudante,vehiculo'],
            'saliente_id' => ['required', 'integer', 'exists:'.($rol === 'vehiculo' ? 'vehicles' : 'users').',id'],
            'entrante_id' => ['required', 'integer', 'exists:'.($rol === 'vehiculo' ? 'vehicles' : 'users').',id'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'motivo_id' => ['nullable', 'integer', 'exists:change_reasons,id', ...($exigirMotivo ? ['required_without:descripcion'] : [])],
            'descripcion' => ['nullable', 'string', 'max:500', ...($exigirMotivo ? ['required_without:motivo_id'] : [])],
        ], [
            'motivo_id.required_without' => 'Elija un motivo o escriba el detalle del cambio.',
            'descripcion.required_without' => 'Elija un motivo o escriba el detalle del cambio.',
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

        $filas = Programacion::pendientes()->enFeriado()
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
            return $this->rechazo('Una programación finalizada ya no se puede modificar.', 'admin.programaciones.index');
        }

        if ($programacion->date->lt(today())) {
            return $this->rechazo('Esta programación ya pasó su fecha: solo se puede finalizar o eliminar.', 'admin.programaciones.index');
        }

        return view('admin.programaciones.edit', [
            'programacion' => $programacion,
            'motivos' => Motivo::orderBy('name')->get(),
            'turnos' => Turno::orderBy('time_start')->get(),
            'vehiculos' => Vehiculo::where('status', true)->orWhere('id', $programacion->vehicle_id)->orderBy('name')->get(),
            'estadoPersonal' => collect(app(MonitorDeProgramacion::class)->deProgramacion($programacion)['personal'])->keyBy('id'),
            'cambios' => $programacion->cambios()->with('usuario')->orderByDesc('id')->get(),
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

        return response()->json($this->respuestaDeRevision($this->cambios->revisar($programacion, $datos)));
    }

    public function update(Request $request, string $id)
    {
        $programacion = Programacion::findOrFail($id);

        if (! $programacion->esProgramado()) {
            return $this->rechazo('Una programación finalizada ya no se puede modificar.', 'admin.programaciones.index');
        }

        $cantidad = $this->cambios->modificar($programacion, $this->validadoCambio($request), auth()->id());

        return $this->exito($cantidad ? 'Programación reprogramada' : 'Programación actualizada', 'admin.programaciones.index');
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

    /* ---------------------------- candidatos para el editor ---------------------------- */

    /** Personas que pueden tomar el lugar de otra en una programación, con el motivo si no están disponibles. */
    public function candidatos(Request $request, string $id)
    {
        $datos = $request->validate(['rol' => ['required', 'in:conductor,ayudante'], 'saliente_id' => ['required', 'integer', 'exists:users,id'], 'turno_id' => ['nullable', 'integer', 'exists:schedules,id']]);
        $programacion = Programacion::with('ayudantes')->findOrFail($id);

        return response()->json(['data' => $this->reglas->candidatos($programacion, $datos['rol'], (int) $datos['saliente_id'], filled($datos['turno_id'] ?? null) ? (int) $datos['turno_id'] : null)]);
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
            'turno_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'vehiculo_id' => ['required', 'integer', 'exists:vehicles,id'],
            'conductor_id' => ['required', 'integer', 'exists:users,id'],
            'ayudantes' => ['required', 'array', 'min:1'],
            'ayudantes.*' => ['required', 'integer', 'exists:users,id'],
            'notas' => ['nullable', 'string', 'max:500'],
            'motivos' => ['nullable', 'array'],
            'motivos.*' => ['array'],
            'motivos.*.motivo_id' => ['nullable', 'integer', 'exists:change_reasons,id'],
            'motivos.*.descripcion' => ['nullable', 'string', 'max:500'],
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
            'vehiculo_id' => $datos['vehiculo_id'] ?? $grupo->vehicle_id,
            'usar_feriados' => $datos['usar_feriados'] ?? [],
            'conductor_id' => $datos['conductor_id'] ?? $grupo->driver_id,
            'ayudantes' => $datos['ayudantes'] ?? $grupo->ayudantes()->pluck('users.id')->all(),
            'grupo_id' => $grupo->id,
            'etiqueta' => $grupo->name,
            'notas' => $datos['notas'] ?? null,
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
            // Feriados que el usuario decide trabajar (los demás se omiten) y ajustes de cada grupo.
            'usar_feriados' => ['nullable', 'array'],
            'usar_feriados.*' => ['date'],
            'detalle' => ['nullable', 'array'],
            'detalle.*.vehiculo_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'detalle.*.conductor_id' => ['nullable', 'integer', 'exists:users,id'],
            'detalle.*.ayudantes' => ['nullable', 'array'],
            'detalle.*.ayudantes.*' => ['nullable', 'integer', 'exists:users,id'],
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

        return $grupos->map(function (GrupoPersonal $g) use ($datos) {
            $ajuste = $datos['detalle'][$g->id] ?? [];
            $ayudantes = array_values(array_filter($ajuste['ayudantes'] ?? [], 'filled'));

            return $this->solicitudDeGrupo(array_filter([
                'inicio' => $datos['inicio'],
                'fin' => $datos['fin'],
                'usar_feriados' => $datos['usar_feriados'] ?? [],
                'vehiculo_id' => $ajuste['vehiculo_id'] ?? null,
                'conductor_id' => $ajuste['conductor_id'] ?? null,
                'ayudantes' => $ayudantes ?: null,
            ], fn ($v) => $v !== null), $g);
        })->all();
    }

    private function respuestaDeRevision(array $revision): array
    {
        $total = array_sum(array_map(fn (array $p) => count($p['fechas']), $revision['plan']));
        $unico = count($revision['plan']) === 1;

        // Un resumen por grupo (programación masiva): cuántos días generaría y cuántos problemas tiene.
        $grupos = collect($revision['plan'])->filter(fn (array $p) => isset($p['grupo_id']))->map(function (array $p) use ($revision, $unico) {
            $suyos = collect($revision['problemas'])->filter(fn (array $x) => $x['grupo'] === ($p['etiqueta'] ?? null) || ($unico && $x['grupo'] === null));

            return ['id' => $p['grupo_id'], 'nombre' => $p['etiqueta'] ?? '', 'dias' => count($p['fechas']), 'problemas' => $suyos->count()];
        })->values()->all();

        return [
            'grupos' => $grupos,
            'ok' => $revision['ok'],
            'errores' => $revision['errores'],
            'sugerencias' => $revision['sugerencias'],
            'avisos' => $revision['avisos'],
            'problemas' => $revision['problemas'],
            'notas' => $revision['notas'],
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
            'grupo_id' => $p->group_id,
            'grupo' => $p->grupo?->name,
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
