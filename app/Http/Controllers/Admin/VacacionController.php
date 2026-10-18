<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Vacacion;
use App\Services\ReglasVacacion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VacacionController extends Controller
{
    use RespondeAjax;

    public function __construct(private readonly ReglasVacacion $reglas)
    {
    }

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.vacaciones.index');
        }

        $filas = Vacacion::with('personal')
            ->orderByRaw("case status when 'pendiente' then 0 else 1 end")
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Vacacion $vacacion) => $this->fila($vacacion));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.vacaciones.create', ['cupo' => config('proyecto.vacaciones.dias_por_anio')]);
    }

    public function store(Request $request)
    {
        $datos = $this->validado($request);
        $this->exigirReglas($datos);

        $vacacion = Vacacion::create($this->paraGuardar($datos) + ['status' => Vacacion::PENDIENTE]);

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
        $this->exigirReglas($datos, $vacacion);

        $vacacion->update($this->paraGuardar($datos));

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

        if (! $vacacion->esPendiente()) {
            return $this->rechazo('Esta solicitud ya fue resuelta.', 'admin.vacaciones.index');
        }

        // No se aprueba algo que ya pasó, ni a quien dejó de tener derecho (inactivo o sin contrato nombrado/permanente).
        if ($vacacion->end_date->lt(today())) {
            return $this->rechazo('No se puede aprobar: el periodo solicitado ya terminó. Rechace o elimine la solicitud.', 'admin.vacaciones.index');
        }

        $persona = $vacacion->personal;
        if (! $persona?->status || ! $this->reglas->tieneDerecho($persona, $vacacion->start_date)) {
            return $this->rechazo('No se puede aprobar: la persona ya no es personal nombrado o con contrato permanente vigente.', 'admin.vacaciones.index');
        }

        $anio = $vacacion->start_date->year;
        $disponibles = $this->reglas->disponiblesParaAprobar($vacacion->user_id, $anio);

        if ($vacacion->days > $disponibles) {
            return $this->rechazo("No se puede aprobar: la persona solo tiene {$disponibles} día(s) disponibles en {$anio}.", 'admin.vacaciones.index');
        }

        $vacacion->update(['status' => Vacacion::APROBADA]);

        return $this->exito('Solicitud aprobada: se descontaron '.$vacacion->days.' día(s)', 'admin.vacaciones.index', ['vacacion' => $this->fila($vacacion->fresh('personal'))]);
    }

    /** Rechazar: no descuenta días y libera las fechas. */
    public function rechazar(string $id)
    {
        $vacacion = Vacacion::findOrFail($id);

        if (! $vacacion->esPendiente()) {
            return $this->rechazo('Esta solicitud ya fue resuelta.', 'admin.vacaciones.index');
        }

        $vacacion->update(['status' => Vacacion::RECHAZADA]);

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

    private function fila(Vacacion $vacacion): array
    {
        $persona = $vacacion->personal;

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
            'restantes' => Vacacion::diasDisponibles($vacacion->user_id, $vacacion->start_date->year),
            'notas' => $vacacion->notes,
            'pendiente' => $vacacion->esPendiente(),
        ];
    }
}
