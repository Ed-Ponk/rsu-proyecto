<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Cambio;
use App\Models\Motivo;
use App\Models\Programacion;
use App\Models\Turno;
use App\Models\Zona;
use App\Services\ReglasProgramacion;
use App\Services\ServicioCambios;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de cambios (Avance 04): lista de cambios hechos a las programaciones, cambio masivo por rango de fechas,
 * detalle con valores previos y nuevos, y eliminación (que restablece los valores iniciales).
 */
class CambioController extends Controller
{
    use RespondeAjax;

    private const TOPE_LISTADO = 5000;

    public function __construct(private readonly ServicioCambios $cambios) {}

    public function index(Request $request)
    {
        if (! $request->ajax()) {
            return view('admin.cambios.index', ['desde' => today()->subDays(30)->toDateString(), 'hasta' => today()->toDateString()]);
        }

        $filtro = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'tipo' => ['nullable', 'in:'.implode(',', array_keys(Cambio::TIPOS))],
        ], ['hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.']);

        $filas = Cambio::with(['usuario', 'programacion:id,date,status'])
            ->when(filled($filtro['desde'] ?? null), fn ($q) => $q->where('created_at', '>=', Carbon::parse($filtro['desde'])->startOfDay()))
            ->when(filled($filtro['hasta'] ?? null), fn ($q) => $q->where('created_at', '<=', Carbon::parse($filtro['hasta'])->endOfDay()))
            ->when(filled($filtro['tipo'] ?? null), fn ($q) => $q->where('type', $filtro['tipo']))
            ->orderByDesc('id')->limit(self::TOPE_LISTADO)->get()
            ->map(fn (Cambio $c) => $this->filaDeCambio($c))
            ->values();

        return response()->json(['data' => $filas]);
    }

    /** Una fila por cambio (una programación), como en el listado de cambios. */
    private function filaDeCambio(Cambio $c): array
    {
        $antes = $c->valor('old');
        $despues = $c->valor('new');
        $p = $c->programacion;

        return [
            'id' => $c->id,
            'lote' => $c->batch_id,
            'tipo' => $c->type,
            'tipo_texto' => $c->tipo_texto,
            'fecha' => $c->created_at?->format('d/m/Y'),
            'hora' => $c->created_at?->format('H:i'),
            'orden' => $c->created_at?->timestamp,
            'antes' => ['titulo' => $antes['titulo'], 'detalle' => $antes['detalle']],
            'despues' => ['titulo' => $despues['titulo'], 'detalle' => $despues['detalle']],
            'usuario' => $c->usuario?->name ?? 'Sistema',
            'correo' => $c->usuario?->email,
            'programacion' => $c->programming_id,
            'fecha_programacion' => $p?->date->format('d/m/Y'),
            'motivo' => $c->reason_name ?? 'Motivo propio',
            'descripcion' => $c->description,
            'masivo' => $c->scope === Cambio::MASIVO,
            'puede_eliminar' => (bool) ($p && $p->esProgramado() && $p->date->gte(today())),
        ];
    }

    /** Ventana «Detalles del cambio #N»: valores previos y nuevos, tipo, usuario, fecha y programación afectada. */
    public function detalle(string $cambio)
    {
        $cambio = Cambio::with(['usuario', 'programacion.zona', 'programacion.turno', 'programacion.grupo'])->findOrFail($cambio);

        return view('admin.cambios.detalle', [
            'cambio' => $cambio, 'antes' => $cambio->valor('old'), 'despues' => $cambio->valor('new'),
            'hermanos' => Cambio::where('batch_id', $cambio->batch_id)->count(),
        ]);
    }

    /* ---------------------------- cambio masivo ---------------------------- */

    public function create()
    {
        return view('admin.cambios.create', [
            'desde' => today()->toDateString(),
            'hasta' => today()->addDays(30)->toDateString(),
            'zonas' => Zona::orderBy('name')->get(['id', 'name']),
            'turnos' => Turno::orderBy('time_start')->get(),
            'motivos' => Motivo::orderBy('name')->get(),
        ]);
    }

    public function validar(Request $request)
    {
        $revision = $this->cambios->revisarMasivo($this->validado($request, false));

        return response()->json([
            'ok' => $revision['ok'], 'errores' => $revision['errores'], 'sugerencias' => $revision['sugerencias'],
            'avisos' => $revision['avisos'], 'total' => $revision['total'], 'problemas' => $revision['problemas'], 'notas' => $revision['notas'],
        ]);
    }

    public function store(Request $request)
    {
        [$total, $lote] = $this->cambios->masivo($this->validado($request), auth()->id());

        return $this->exito($total === 1 ? 'Se cambió 1 programación' : "Se cambiaron {$total} programaciones", 'admin.cambios.index', ['lote' => $lote]);
    }

    /** El motivo solo hace falta al guardar: para validar la disponibilidad todavía no se pide. */
    private function validado(Request $request, bool $exigirMotivo = true): array
    {
        $tipo = $request->input('rol');
        $tabla = match ($tipo) {
            Cambio::TURNO => 'schedules',
            Cambio::VEHICULO => 'vehicles',
            default => 'users',
        };

        $datos = $request->validate([
            'rol' => ['required', 'in:'.implode(',', array_keys(Cambio::TIPOS))],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'zona_id' => ['nullable', 'integer', 'exists:zones,id'],
            'zona_ids' => ['nullable', 'array'],
            'zona_ids.*' => ['integer', 'exists:zones,id'],
            'saliente_id' => ['required', 'integer', "exists:{$tabla},id"],
            'entrante_id' => ['required', 'integer', "exists:{$tabla},id"],
            'motivo_id' => ['nullable', 'integer', 'exists:change_reasons,id', ...($exigirMotivo ? ['required_without:descripcion'] : [])],
            'descripcion' => ['nullable', 'string', 'max:500', ...($exigirMotivo ? ['required_without:motivo_id'] : [])],
        ], [
            'rol.required' => 'Elija qué desea cambiar.',
            'saliente_id.required' => 'Seleccione el valor actual que se va a cambiar.',
            'entrante_id.required' => 'Seleccione el valor nuevo.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'motivo_id.required_without' => 'Elija un motivo o escriba el detalle del cambio.',
            'descripcion.required_without' => 'Elija un motivo o escriba el detalle del cambio.',
        ]);

        if (Carbon::parse($datos['desde'])->diffInDays(Carbon::parse($datos['hasta'])) + 1 > ReglasProgramacion::MAX_DIAS_RANGO) {
            throw ValidationException::withMessages(['hasta' => 'El rango no puede superar '.ReglasProgramacion::MAX_DIAS_RANGO.' días.']);
        }

        return $datos;
    }

    /* ---------------------------- detalle y eliminación ---------------------------- */

    public function show(string $lote)
    {
        $filas = Cambio::with(['usuario', 'programacion.zona', 'programacion.turno', 'programacion.grupo'])
            ->where('batch_id', $lote)->get()->sortBy(fn (Cambio $c) => $c->programacion?->date)->values();

        abort_if($filas->isEmpty(), 404);

        return view('admin.cambios.show', ['cambio' => $this->fila($filas), 'filas' => $filas]);
    }

    public function destroy(Request $request, string $lote)
    {
        // Con programacion_id solo se deshace lo de esa programación y con cambio_id solo ese cambio (desde la ventana de modificar).
        $filas = Cambio::where('batch_id', $lote)
            ->when($request->filled('programacion_id'), fn ($q) => $q->where('programming_id', (int) $request->input('programacion_id')))
            ->when($request->filled('cambio_id'), fn ($q) => $q->where('id', (int) $request->input('cambio_id')))
            ->get();

        if ($filas->isEmpty()) {
            return $this->rechazo('Ese cambio ya no existe.', 'admin.cambios.index', 404);
        }

        $total = $this->cambios->eliminar($filas);

        return $this->exito($total === 1
            ? 'Cambio eliminado: la programación recuperó su valor inicial'
            : "Cambio eliminado: {$total} programaciones recuperaron su valor inicial", 'admin.cambios.index');
    }

    /** Una fila del listado: todos los cambios de un mismo lote juntos. */
    private function fila(Collection $lote): array
    {
        /** @var Cambio $primero */
        $primero = $lote->first();
        $hoy = today();
        $deshacibles = $lote->every(fn (Cambio $c) => $c->programacion && $c->programacion->esProgramado() && $c->programacion->date->gte($hoy));

        return [
            'lote' => $primero->batch_id,
            'fecha' => $primero->created_at?->format('d/m/Y H:i'),
            'orden' => $primero->created_at?->timestamp,
            'tipo' => $lote->pluck('type')->unique()->count() > 1 ? 'varios' : $primero->type,
            'tipo_texto' => $lote->pluck('type')->unique()->count() > 1 ? 'Varios' : $primero->tipo_texto,
            'usuario' => $primero->usuario?->name ?? 'Sistema',
            'motivo' => $primero->reason_name ?? 'Motivo propio',
            'descripcion' => $primero->description,
            'programaciones' => $lote->count(),
            'antes' => $primero->old_label,
            'despues' => $primero->new_label,
            'uniforme' => $lote->pluck('type')->unique()->count() === 1 && $lote->pluck('old_label')->unique()->count() === 1 && $lote->pluck('new_label')->unique()->count() === 1,
            'puede_eliminar' => $deshacibles,
            'primera' => $lote->map(fn (Cambio $c) => $c->programacion?->date)->filter()->min()?->format('d/m/Y'),
            'ultima' => $lote->map(fn (Cambio $c) => $c->programacion?->date)->filter()->max()?->format('d/m/Y'),
        ];
    }

    /** Estado de la programación para mostrarlo en el detalle. */
    public static function estadoTexto(?Programacion $programacion): string
    {
        return $programacion ? (Programacion::ESTADOS[$programacion->status] ?? $programacion->status) : '—';
    }
}
