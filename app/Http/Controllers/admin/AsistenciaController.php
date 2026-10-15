<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Personal;
use App\Services\ResolverTurno;
use App\Services\ServicioAsistencia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsistenciaController extends Controller
{
    use RespondeAjax;

    public function __construct(private readonly ServicioAsistencia $servicio)
    {
    }

    /** Por defecto muestra las asistencias de hoy; admite rango de fechas y búsqueda por DNI o nombre. */
    public function index(Request $request)
    {
        if (! $request->ajax()) {
            return view('admin.asistencias.index', ['hoy' => today()->toDateString()]);
        }

        $desde = $this->fechaOpcional($request->input('desde')) ?? today();
        $hasta = $this->fechaOpcional($request->input('hasta')) ?? $desde->copy();

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $texto = trim((string) $request->input('buscar'));

        $filas = Asistencia::with(['personal.tipo', 'turno'])
            ->whereBetween('date', [$desde->toDateString(), $hasta->toDateString()])
            ->when($texto !== '', fn ($q) => $q->whereHas('personal', fn ($p) => $p
                ->where('dni', 'like', "%{$texto}%")
                ->orWhere('name', 'like', "%{$texto}%")
                ->orWhere('last_name', 'like', "%{$texto}%")))
            ->orderByDesc('date')->orderByDesc('time')->orderByDesc('id')
            ->get()
            ->map(fn (Asistencia $a) => $this->fila($a));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.asistencias.create', $this->catalogos());
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNotNull('usertype_id')],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'status' => ['required', Rule::in([Asistencia::PRESENTE, Asistencia::AUSENTE])],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'user_id.required' => 'Seleccione a la persona.',
            'date.before_or_equal' => 'No se puede registrar asistencia en una fecha futura.',
            'time.date_format' => 'La hora debe tener el formato HH:MM.',
        ]);

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
        return view('admin.asistencias.edit', $this->catalogos() + [
            'asistencia' => Asistencia::with(['personal.tipo', 'turno'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $asistencia = Asistencia::findOrFail($id);

        $datos = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'status' => ['required', Rule::in([Asistencia::PRESENTE, Asistencia::AUSENTE])],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'date.before_or_equal' => 'No se puede registrar asistencia en una fecha futura.',
        ]);

        $momento = Carbon::parse("{$datos['date']} {$datos['time']}");

        if ($momento->greaterThan(now()->addMinute())) {
            throw ValidationException::withMessages(['time' => 'No se puede registrar asistencia en una hora futura.']);
        }

        $this->validarContraOtrasDelDia($asistencia, $datos['date'], $datos['status']);

        $diaAnterior = $asistencia->date->toDateString();

        $asistencia->update([
            'date' => $datos['date'],
            'time' => $momento->format('H:i:s'),
            'schedule_id' => ResolverTurno::paraHora($momento)?->id,
            'status' => $datos['status'],
            'type' => $datos['status'] === Asistencia::AUSENTE ? null : $asistencia->type,
            'notes' => $datos['notes'] ?? null,
        ]);

        // Al mover una marcación hay que reordenar entrada/salida en el día nuevo y en el anterior.
        Asistencia::renumerarDia($asistencia->user_id, $datos['date']);

        if ($diaAnterior !== $datos['date']) {
            Asistencia::renumerarDia($asistencia->user_id, $diaAnterior);
        }

        return $this->exito('Asistencia actualizada', 'admin.asistencias.index', [
            'asistencia' => $this->fila($asistencia->fresh(['personal.tipo', 'turno'])),
        ]);
    }

    public function destroy(string $id)
    {
        $asistencia = Asistencia::findOrFail($id);
        $idPersonal = $asistencia->user_id;
        $dia = $asistencia->date->toDateString();

        $asistencia->delete();
        Asistencia::renumerarDia($idPersonal, $dia);

        return $this->exito('Asistencia eliminada', 'admin.asistencias.index');
    }

    /** Respuesta JSON con la ficha de la persona y lo que se registraría (tipo y turno). */
    public function vistaPrevia(Request $request)
    {
        $datos = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNotNull('usertype_id')],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        return response()->json($this->servicio->vistaPrevia(
            Personal::with('tipo')->findOrFail($datos['user_id']),
            Carbon::parse($datos['date'])->toDateString(),
            $datos['time'],
            $request->integer('ignorar') ?: null,
        ));
    }

    /* ------------------------------------------------------------------ */

    /** Entre las marcaciones del mismo día no pueden mezclarse ausencias y asistencias. */
    private function validarContraOtrasDelDia(Asistencia $asistencia, string $dia, string $estado): void
    {
        $otras = Asistencia::where('user_id', $asistencia->user_id)->whereDate('date', $dia)->whereKeyNot($asistencia->id);
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

    private function catalogos(): array
    {
        return ['personal' => Personal::with('tipo')->activos()->orderBy('last_name')->orderBy('name')->get()];
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
            'dni' => $persona?->dni,
            'persona' => $persona?->nombre_completo,
            'funcion' => $persona?->tipo?->name,
            'fecha' => $asistencia->date->format('d/m/Y'),
            'hora' => substr($asistencia->time, 0, 5),
            'orden' => $asistencia->date->format('Y-m-d').' '.$asistencia->time,
            'turno' => $asistencia->turno?->name,
            'tipo' => $asistencia->type,
            'estado' => $asistencia->status,
            'origen' => $asistencia->source,
            'notas' => $asistencia->notes,
        ];
    }
}
