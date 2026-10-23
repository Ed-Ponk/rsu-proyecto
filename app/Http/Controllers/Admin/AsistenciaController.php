<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AsistenciaRequest;
use App\Models\Asistencia;
use App\Models\Personal;
use App\Services\ResolverTurno;
use App\Services\ServicioAsistencia;
use App\Support\HashFicticio;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsistenciaController extends Controller
{
    use RespondeAjax;

    public function __construct(private readonly ServicioAsistencia $servicio) {}

    /** Filas máximas que devuelve el modo sin paginar (compatibilidad); la pantalla usa el modo paginado. */
    private const TOPE_SIN_PAGINAR = 2000;

    /** Columnas por las que se puede ordenar (índice de la columna de DataTables => columna de la base). */
    private const ORDENABLES = [0 => 'users.dni', 1 => 'users.last_name', 3 => 'attendances.date', 4 => 'attendances.type', 5 => 'attendances.status', 7 => 'attendances.source'];

    /**
     * Por defecto muestra las asistencias de hoy; admite rango de fechas y búsqueda por DNI o nombre.
     *
     * Con el parámetro `draw` responde en el formato de DataTables "serverSide": solo envía la página pedida y los totales,
     * así el rango de fechas puede ser tan largo como se quiera sin agotar la memoria. Sin `draw` (uso antiguo) devuelve
     * como máximo TOPE_SIN_PAGINAR filas.
     */
    public function index(Request $request)
    {
        if (! $request->routeIs('*.datos') && ! $request->ajax()) {
            return view('admin.asistencias.index', ['hoy' => today()->toDateString()]);
        }

        $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'buscar' => ['nullable', 'string', 'max:60'],
            'search' => ['sometimes', 'array:value,regex'],
            'search.value' => ['nullable', 'string', 'max:60'],
            'search.regex' => ['nullable', 'boolean'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], ['desde.date' => 'La fecha inicial no es válida.', 'hasta.date' => 'La fecha final no es válida.']);

        $desde = $this->fechaOpcional($request->input('desde')) ?? today();
        $hasta = $this->fechaOpcional($request->input('hasta')) ?? $desde->copy();

        if ($hasta->lt($desde)) {
            throw ValidationException::withMessages(['hasta' => 'La fecha final no puede ser anterior a la inicial.']);
        }

        $enRango = fn () => Asistencia::query()
            ->where('attendances.date', '>=', $desde->toDateString())
            ->where('attendances.date', '<=', $hasta->toDateString());

        $texto = trim((string) $request->input('buscar'));
        $buscador = trim((string) $request->input('search.value'));

        $consulta = $enRango()->join('users', 'users.id', '=', 'attendances.user_id')->select('attendances.*');

        foreach (array_filter([$texto, $buscador]) as $palabra) {
            $patron = '%'.addcslashes($palabra, '%_\\').'%';
            $consulta->where(fn ($q) => $q->where('users.dni', 'like', $patron)
                ->orWhere('users.name', 'like', $patron)
                ->orWhere('users.last_name', 'like', $patron));
        }

        if (! $request->has('draw')) {
            $filas = $consulta->with(['personal.tipo', 'turno'])
                ->orderByDesc('attendances.date')->orderByDesc('attendances.time')->orderByDesc('attendances.id')
                ->limit(self::TOPE_SIN_PAGINAR)->get()
                ->map(fn (Asistencia $a) => $this->fila($a));

            return response()->json(['data' => $filas, 'truncado' => $filas->count() >= self::TOPE_SIN_PAGINAR]);
        }

        $columna = self::ORDENABLES[(int) $request->input('order.0.column', 3)] ?? 'attendances.date';
        $sentido = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';

        $total = $enRango()->count();
        $filtrados = (clone $consulta)->count();

        $filas = (clone $consulta)->with(['personal.tipo', 'turno'])
            ->orderBy($columna, $sentido)
            ->when($columna === 'attendances.date', fn ($q) => $q->orderBy('attendances.time', $sentido))
            ->orderBy('attendances.id', $sentido)
            ->offset((int) $request->input('start', 0))->limit((int) $request->input('length', 10))
            ->get()
            ->map(fn (Asistencia $a) => $this->fila($a));

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtrados,
            'data' => $filas,
        ]);
    }

    public function create()
    {
        return view('admin.asistencias.create');
    }

    public function store(AsistenciaRequest $request)
    {
        $datos = $request->datos();

        $asistencia = $this->servicio->registrar(
            Personal::findOrFail($datos['user_id']),
            Carbon::parse("{$datos['date']} {$datos['time']}"),
            $datos['status'],
            $datos['notes'] ?? null,
            'admin',
            auth()->id(),
        );

        return $this->exito('Asistencia registrada', 'admin.asistencias.index', [
            'asistencia' => $this->fila($asistencia->load(['personal.tipo', 'turno'])),
        ]);
    }

    public function edit(string $id)
    {
        return view('admin.asistencias.edit', [
            'asistencia' => Asistencia::with(['personal.tipo', 'turno'])->findOrFail($id),
        ]);
    }

    public function update(AsistenciaRequest $request, string $id)
    {
        $asistencia = Asistencia::findOrFail($id);

        $datos = $request->datos();

        $momento = Carbon::parse("{$datos['date']} {$datos['time']}");

        if ($momento->greaterThan(now()->addMinute())) {
            throw ValidationException::withMessages(['time' => 'No se puede registrar asistencia en una hora futura.']);
        }

        DB::transaction(function () use ($asistencia, $datos, $momento) {
            // Se bloquea a la persona: una edición y una marcación simultáneas no se pisan.
            Personal::whereKey($asistencia->user_id)->lockForUpdate()->first();
            $asistencia->refresh();

            $this->validarContraOtrasDelDia($asistencia, $datos['date'], $datos['status']);

            $turnoAnterior = [$asistencia->schedule_id, $asistencia->shift_date?->toDateString()];

            if ($datos['status'] === Asistencia::AUSENTE) {
                // Mismas reglas que al registrar: contrato vigente y sin vacaciones en la fecha nueva.
                $this->servicio->verificarVigencia($asistencia->personal, $momento->copy()->startOfDay(), 'date');

                $ubicacion = ['schedule_id' => ResolverTurno::paraHora($momento)?->id, 'shift_date' => $datos['date'], 'type' => null, 'late_minutes' => null, 'shift_start' => null];
            } else {
                // Misma regla que al registrar (una entrada y una salida por turno), sin contarse a sí misma.
                $marcacion = $this->servicio->resolverMarcacion($asistencia->personal, $momento, $asistencia->id);

                if ($marcacion['problema']) {
                    throw ValidationException::withMessages(['time' => $marcacion['problema']]);
                }

                $this->servicio->verificarVigencia(
                    $asistencia->personal,
                    $this->servicio->fechaDeVigencia($marcacion['tipo'], $marcacion['jornada'], $momento->copy()->startOfDay()),
                    'date',
                );

                // Si sigue en el mismo turno conserva la hora de inicio con la que se calculó; si cambia de turno, se recalcula.
                $mismoTurno = $marcacion['turno']->id === $asistencia->schedule_id && $marcacion['jornada'] === $asistencia->shift_date?->toDateString();
                $ubicacion = [
                    'schedule_id' => $marcacion['turno']->id, 'shift_date' => $marcacion['jornada'],
                    'type' => null, 'shift_start' => $mismoTurno ? $asistencia->shift_start : null,
                ];
            }

            $asistencia->update($ubicacion + [
                'date' => $datos['date'],
                'time' => $momento->format('H:i:s'),
                'status' => $datos['status'],
                'notes' => $datos['notes'] ?? null,
            ]);

            // Al mover una marcación se reordena entrada/salida (y tardanza) en su turno nuevo y en el anterior.
            Asistencia::renumerarTurno($asistencia->user_id, $asistencia->schedule_id, $asistencia->shift_date?->toDateString());

            if ($turnoAnterior !== [$asistencia->schedule_id, $asistencia->shift_date?->toDateString()]) {
                Asistencia::renumerarTurno($asistencia->user_id, ...$turnoAnterior);
            }
        });

        return $this->exito('Asistencia actualizada', 'admin.asistencias.index', [
            'asistencia' => $this->fila($asistencia->fresh(['personal.tipo', 'turno'])),
        ]);
    }

    public function destroy(string $id)
    {
        $asistencia = Asistencia::findOrFail($id);

        DB::transaction(function () use ($asistencia) {
            Personal::whereKey($asistencia->user_id)->lockForUpdate()->first();

            $asistencia->refresh();
            [$idPersonal, $idTurno, $jornada] = [$asistencia->user_id, $asistencia->schedule_id, $asistencia->shift_date?->toDateString()];

            $asistencia->delete();
            Asistencia::renumerarTurno($idPersonal, $idTurno, $jornada);
        });

        return $this->exito('Asistencia eliminada', 'admin.asistencias.index');
    }

    /** Respuesta JSON con la ficha de la persona y lo que se registraría (tipo y turno). */
    public function vistaPrevia(Request $request)
    {
        $datos = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNotNull('usertype_id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        return response()->json($this->servicio->vistaPrevia(
            Personal::with('tipo')->findOrFail($datos['user_id']),
            Carbon::parse($datos['date'])->toDateString(),
            $datos['time'],
            $request->integer('ignorar') ?: null,
        ));
    }

    /* ---------------------------------------------------------- Marcación con DNI */

    /** Pantalla de quiosco: el personal registra su entrada o salida con su DNI y su clave de marcación. */
    public function marcacion()
    {
        return view('admin.asistencias.marcacion');
    }

    /**
     * Estado del quiosco: hora del servidor, turno vigente y últimas marcaciones de hoy.
     * El reloj de la pantalla se sincroniza con esta hora (es la que se guarda) y el turno lo decide el backend.
     */
    public function estadoMarcacion()
    {
        $ahora = now();
        $turno = ResolverTurno::paraHora($ahora);

        $ultimas = Asistencia::with('personal.tipo')
            ->where('date', $ahora->toDateString())
            ->where('status', Asistencia::PRESENTE)
            ->orderByDesc('time')->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Asistencia $a) => [
                'persona' => $a->personal?->nombre_completo,
                'funcion' => $a->personal?->tipo?->name,
                'hora' => substr($a->time, 0, 5),
                'tipo' => $a->type,
                'tardanza' => $a->late_minutes,
                'origen' => $a->source,
            ]);

        return response()->json([
            'servidor' => $ahora->toIso8601String(),
            'zona' => config('app.timezone'),
            'turno' => $turno?->etiqueta,
            'ultimas' => $ultimas,
        ]);
    }

    /** Registra la marcación: valida credenciales, limita intentos y aplica las mismas reglas que el registro manual. */
    public function marcar(Request $request)
    {
        $request->validate(['dni' => ['required', 'string']]);
        $request->merge(['dni' => preg_replace('/\D/', '', (string) $request->input('dni'))]);

        $datos = $request->validate([
            'dni' => ['required', 'digits:8'],
            'password' => ['required', 'string', 'max:60'],
        ], [
            'dni.required' => 'Ingrese su DNI.',
            'dni.digits' => 'El DNI debe tener exactamente 8 dígitos.',
            'password.required' => 'Ingrese su clave de marcación.',
        ]);

        $clave = 'marcacion:'.$request->ip().'|'.$datos['dni'];
        $intentos = (int) config('proyecto.asistencia.intentos_marcacion', 5);

        if (RateLimiter::tooManyAttempts($clave, $intentos)) {
            throw ValidationException::withMessages([
                'password' => 'Demasiados intentos fallidos. Espere '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        $persona = Personal::with('tipo')->where('dni', $datos['dni'])->first();

        // Mismo mensaje si el DNI no existe o la clave no coincide: no se revela qué DNI está registrado.
        if (! $persona) {
            HashFicticio::verificar($datos['password']);
        }

        if (! $persona || ! Hash::check($datos['password'], $persona->password)) {
            RateLimiter::hit($clave, 60);

            throw ValidationException::withMessages(['password' => 'DNI o clave incorrectos.']);
        }

        RateLimiter::clear($clave);

        if ($espera = $this->servicio->segundosParaVolverAMarcar($persona)) {
            throw ValidationException::withMessages([
                'dni' => "Ya registró una marcación hace instantes. Espere {$espera} segundos para marcar de nuevo.",
            ]);
        }

        // Activo, con contrato vigente y coherencia del día: lo valida el servicio (errores en el campo DNI).
        $asistencia = $this->servicio->registrar($persona, now(), Asistencia::PRESENTE, null, 'marcacion', auth()->id(), 'dni');
        $asistencia->load('turno');

        $esEntrada = $asistencia->type === Asistencia::ENTRADA;

        return response()->json([
            'mensaje' => $esEntrada
                ? ($asistencia->late_minutes ? 'Entrada registrada con '.Asistencia::textoDeDuracion($asistencia->late_minutes).' de tardanza' : 'Entrada registrada a tiempo')
                : 'Salida registrada',
            'marcacion' => [
                'tipo' => $asistencia->type,
                'hora' => substr($asistencia->time, 0, 5),
                'fecha' => $asistencia->date->format('d/m/Y'),
                'turno' => $asistencia->turno?->name,
                'tardanza' => $asistencia->late_minutes,
                'persona' => $persona->nombre_completo,
                'dni' => $persona->dni,
                'funcion' => $persona->tipo?->name,
                'foto' => $persona->url_foto,
                'iniciales' => mb_strtoupper(mb_substr($persona->name, 0, 1).mb_substr((string) $persona->last_name, 0, 1)),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** Entre las marcaciones del mismo día no pueden mezclarse ausencias y asistencias. */
    private function validarContraOtrasDelDia(Asistencia $asistencia, string $dia, string $estado): void
    {
        $otras = Asistencia::where('user_id', $asistencia->user_id)->where('date', $dia)->whereKeyNot($asistencia->id);
        $hayPresencia = (clone $otras)->where('status', Asistencia::PRESENTE)->exists();
        $hayAusencia = (clone $otras)->where('status', Asistencia::AUSENTE)->exists();

        $problema = match (true) {
            $estado === Asistencia::AUSENTE && $hayPresencia => 'Ese día ya tiene marcaciones; no puede figurar como ausente.',
            $estado === Asistencia::AUSENTE && $hayAusencia => 'Ya tiene una ausencia registrada ese día.',
            $estado === Asistencia::PRESENTE && $hayAusencia => 'Ese día figura como ausente. Elimine primero la ausencia.',
            default => null,
        };

        if ($problema) {
            throw ValidationException::withMessages(['status' => $problema]);
        }
    }

    private function fechaOpcional(?string $texto): ?Carbon
    {
        try {
            return filled($texto) ? Carbon::parse($texto)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fila(Asistencia $asistencia): array
    {
        $persona = $asistencia->personal;

        return [
            'id' => $asistencia->id,
            'version' => (int) $asistencia->version,
            'dni' => $persona?->dni,
            'persona' => $persona?->nombre_completo,
            'funcion' => $persona?->tipo?->name,
            'fecha' => $asistencia->date->format('d/m/Y'),
            'hora' => substr($asistencia->time, 0, 5),
            'orden' => $asistencia->date->format('Y-m-d').' '.$asistencia->time,
            'turno' => $asistencia->turno?->name,
            'tipo' => $asistencia->type,
            'tardanza' => $asistencia->late_minutes,
            'estado' => $asistencia->status,
            'origen' => $asistencia->source,
            'notas' => $asistencia->notes,
        ];
    }
}
