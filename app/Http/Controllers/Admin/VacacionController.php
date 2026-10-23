<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\Vacacion;
use App\Services\ReglasVacacion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacacionController extends Controller
{
    use RespondeAjax;

    public function __construct(private readonly ReglasVacacion $reglas) {}

    public function index()
    {
        if (! request()->routeIs('*.datos') && ! request()->ajax()) {
            return view('admin.vacaciones.index');
        }

        // Una sola consulta para los días aprobados de todos (antes eran dos consultas por cada fila).
        $aprobados = Vacacion::diasAprobadosAgrupados();

        $filas = Vacacion::with('personal')
            ->orderByRaw("case status when 'pendiente' then 0 else 1 end")
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Vacacion $vacacion) => $this->fila($vacacion, $aprobados));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.vacaciones.create', ['cupo' => config('proyecto.vacaciones.dias_por_anio')]);
    }

    public function store(Request $request)
    {
        $datos = $this->validado($request);

        // Se bloquea a la persona mientras se revisan las reglas y se guarda: dos solicitudes simultáneas no se cruzan.
        $vacacion = DB::transaction(function () use ($datos) {
            Personal::whereKey($datos['user_id'])->lockForUpdate()->first();
            $this->exigirReglas($datos);

            return Vacacion::create($this->paraGuardar($datos) + ['status' => Vacacion::PENDIENTE]);
        });

        return $this->exito('Solicitud de vacaciones registrada', 'admin.vacaciones.index', ['vacacion' => $this->fila($vacacion->load('personal'))]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        $vacacion = Vacacion::with('personal')->findOrFail($id);

        if (! $vacacion->esPendiente()) {
            return $this->rechazo('Solo las solicitudes pendientes se pueden editar.', 'admin.vacaciones.index');
        }

        return view('admin.vacaciones.edit', ['vacacion' => $vacacion, 'cupo' => config('proyecto.vacaciones.dias_por_anio')]);
    }

    public function update(Request $request, string $id)
    {
        $vacacion = Vacacion::findOrFail($id);

        if (! $vacacion->esPendiente()) {
            return $this->rechazo('Solo las solicitudes pendientes se pueden editar.', 'admin.vacaciones.index');
        }

        // La persona no cambia al editar: se corrigen los días, las fechas o las notas.
        $datos = $this->validado($request, $vacacion->user_id);

        $resultado = DB::transaction(function () use ($vacacion, $datos) {
            Personal::whereKey($vacacion->user_id)->lockForUpdate()->first();
            $vacacion->refresh();

            if (! $vacacion->esPendiente()) {
                return false;   // otra persona la resolvió mientras se editaba
            }

            $this->exigirReglas($datos, $vacacion);
            $vacacion->update($this->paraGuardar($datos));

            return true;
        });

        if (! $resultado) {
            return $this->rechazo('Esta solicitud ya fue resuelta y no se puede editar.', 'admin.vacaciones.index');
        }

        return $this->exito('Solicitud de vacaciones actualizada', 'admin.vacaciones.index', ['vacacion' => $this->fila($vacacion->fresh('personal'))]);
    }

    public function destroy(string $id)
    {
        $vacacion = Vacacion::findOrFail($id);

        if (! $vacacion->esPendiente()) {
            return $this->rechazo('Solo las solicitudes pendientes se pueden eliminar.', 'admin.vacaciones.index');
        }

        $vacacion->delete();

        return $this->exito('Solicitud eliminada', 'admin.vacaciones.index');
    }

    /** Aprobar: descuenta los días del cupo del año y deja a la persona fuera de las programaciones de esas fechas. */
    public function aprobar(string $id)
    {
        $vacacion = Vacacion::findOrFail($id);

        // Con la persona bloqueada, dos aprobaciones simultáneas no pueden pasarse del cupo anual.
        $error = DB::transaction(function () use ($vacacion) {
            Personal::whereKey($vacacion->user_id)->lockForUpdate()->first();
            $vacacion->refresh();

            if (! $vacacion->esPendiente()) {
                return 'Esta solicitud ya fue resuelta.';
            }

            // No se aprueba algo que ya pasó, ni a quien dejó de tener derecho (inactivo o sin contrato nombrado/permanente).
            if ($vacacion->end_date->lt(today())) {
                return 'No se puede aprobar: el periodo solicitado ya terminó. Rechace o elimine la solicitud.';
            }

            $persona = $vacacion->personal;
            if (! $persona?->status || ! $this->reglas->tieneDerecho($persona, $vacacion->start_date)) {
                return 'No se puede aprobar: la persona ya no es personal nombrado o con contrato permanente vigente.';
            }

            $anio = $vacacion->start_date->year;
            $disponibles = $this->reglas->disponiblesParaAprobar($vacacion->user_id, $anio);

            if ($vacacion->days > $disponibles) {
                return "No se puede aprobar: la persona solo tiene {$disponibles} día(s) disponibles en {$anio}.";
            }

            $vacacion->update(['status' => Vacacion::APROBADA, 'resolved_by' => auth()->id(), 'resolved_at' => now()]);

            return null;
        });

        if ($error) {
            return $this->rechazo($error, 'admin.vacaciones.index');
        }

        return $this->exito('Solicitud aprobada: se descontaron '.$vacacion->days.' día(s)', 'admin.vacaciones.index', ['vacacion' => $this->fila($vacacion->fresh('personal'))]);
    }

    /** Rechazar: no descuenta días y libera las fechas. */
    public function rechazar(string $id)
    {
        $vacacion = Vacacion::findOrFail($id);

        $resuelta = DB::transaction(function () use ($vacacion) {
            $vacacion->refresh();

            if (! $vacacion->esPendiente()) {
                return true;
            }

            $vacacion->update(['status' => Vacacion::RECHAZADA, 'resolved_by' => auth()->id(), 'resolved_at' => now()]);

            return false;
        });

        if ($resuelta) {
            return $this->rechazo('Esta solicitud ya fue resuelta.', 'admin.vacaciones.index');
        }

        return $this->exito('Solicitud rechazada', 'admin.vacaciones.index', ['vacacion' => $this->fila($vacacion->fresh('personal'))]);
    }

    private function validado(Request $request, ?int $idPersonal = null): array
    {
        $datos = $request->validate([
            'user_id' => $idPersonal ? ['nullable'] : ['required', 'integer', 'exists:users,id'],
            'days' => ['required', 'integer', 'min:1', 'max:'.config('proyecto.vacaciones.dias_por_anio')],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'user_id.required' => 'Seleccione al personal.',
            'days.required' => 'Indique los días solicitados.',
            'days.min' => 'Debe solicitar al menos 1 día.',
            'days.max' => 'No puede solicitar más de :max días.',
            'start_date.required' => 'Indique la fecha de inicio.',
        ]);

        if ($idPersonal) {
            $datos['user_id'] = $idPersonal;
        }

        return $datos;
    }

    private function exigirReglas(array $datos, ?Vacacion $actual = null): void
    {
        $errores = $this->reglas->revisar($datos, $actual);

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    /** La fecha de término no se escribe: se calcula con el inicio y los días. */
    private function paraGuardar(array $datos): array
    {
        $inicio = Carbon::parse($datos['start_date']);

        return [
            'user_id' => $datos['user_id'],
            'start_date' => $inicio->toDateString(),
            'end_date' => Vacacion::fechaFin($inicio, (int) $datos['days'])->toDateString(),
            'days' => (int) $datos['days'],
            'notes' => filled($datos['notes'] ?? null) ? trim($datos['notes']) : null,
        ];
    }

    /** @param array<string, int>|null $aprobados días aprobados por "persona-año" (si ya se calcularon para todo el listado) */
    private function fila(Vacacion $vacacion, ?array $aprobados = null): array
    {
        $persona = $vacacion->personal;
        $anio = $vacacion->start_date->year;
        $cupo = (int) config('proyecto.vacaciones.dias_por_anio');

        return [
            'id' => $vacacion->id,
            'dni' => $persona?->dni,
            'empleado' => $persona?->nombre_completo,
            'solicitud' => $vacacion->created_at?->format('d/m/Y'),
            'orden_inicio' => $vacacion->start_date->toDateString(),
            'inicio' => $vacacion->start_date->format('d/m/Y'),
            'fin' => $vacacion->end_date->format('d/m/Y'),
            'dias' => $vacacion->days,
            'estado' => $vacacion->status,
            'estado_texto' => Vacacion::ESTADOS[$vacacion->status] ?? $vacacion->status,
            // Cupo que le queda a la persona en el año de estas vacaciones (el año actual para las de este año).
            'restantes' => $aprobados === null
                ? Vacacion::diasDisponibles($vacacion->user_id, $anio)
                : max(0, $cupo - ($aprobados[$vacacion->user_id.'-'.$anio] ?? 0)),
            'notas' => $vacacion->notes,
            'pendiente' => $vacacion->esPendiente(),
        ];
    }
}
