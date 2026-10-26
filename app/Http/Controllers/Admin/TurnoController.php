<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuscaEnCatalogo;
use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Programacion;
use App\Models\Turno;
use App\Support\UsoEnProgramacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TurnoController extends Controller
{
    use BuscaEnCatalogo;
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.turnos.index', ['huecos' => Turno::huecos()]);
        }

        $filas = Turno::withCount('asistencias')
            ->orderBy('time_start')
            ->get()
            ->map(fn (Turno $turno) => $this->fila($turno));

        return response()->json(['data' => $filas, 'huecos' => Turno::huecos()]);
    }

    /** Buscador de turnos (grupos de personal). */
    public function buscar(Request $request)
    {
        return $this->respuestaDeBusqueda(
            $request,
            Turno::orderBy('time_start')->get(),
            fn (Turno $t) => $t->name,
            fn (Turno $t) => self::itemDeBuscador($t),
        );
    }

    public static function itemDeBuscador(Turno $turno): array
    {
        return [
            'id' => $turno->id,
            'titulo' => $turno->name,
            'detalle' => substr($turno->time_start, 0, 5).' - '.substr($turno->time_end, 0, 5),
            'iniciales' => mb_strtoupper(mb_substr($turno->name, 0, 2)),
            'insignia' => null,
        ];
    }

    public function create()
    {
        return view('admin.turnos.create');
    }

    public function store(Request $request)
    {
        $turno = Turno::create($this->validado($request));

        return $this->exito('Turno registrado', 'admin.turnos.index', ['turno' => $this->fila($turno)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.turnos.edit', ['turno' => Turno::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $turno = Turno::findOrFail($id);
        $antes = [$turno->time_start, $turno->time_end];
        $turno->update($this->validado($request, $turno->id));

        // Las programaciones pendientes siguen apuntando a este turno: con otro horario cambia su descanso y su asistencia.
        $pendientes = Programacion::where('shift_id', $turno->id)->where('status', Programacion::PROGRAMADO)->where('date', '>=', today()->toDateString())->count();
        $cambioHorario = $antes !== [$turno->fresh()->time_start, $turno->fresh()->time_end];

        return $this->exito('Turno actualizado', 'admin.turnos.index', ['turno' => $this->fila($turno->fresh())]
            + ($pendientes && $cambioHorario ? ['aviso' => "Hay {$pendientes} programación(es) pendiente(s) con este turno: ahora usan el nuevo horario. Revise que sigan respetando el descanso entre turnos."] : []));
    }

    public function destroy(string $id)
    {
        $turno = Turno::withCount('asistencias')->findOrFail($id);

        if ($turno->asistencias_count > 0) {
            return $this->rechazo('No se puede eliminar el turno porque tiene asistencias registradas.', 'admin.turnos.index');
        }

        if ($uso = UsoEnProgramacion::deTurno($turno->id)) {
            return $this->rechazo("No se puede eliminar el turno porque está en {$uso}.", 'admin.turnos.index');
        }

        $turno->delete();

        return $this->exito('Turno eliminado', 'admin.turnos.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('schedules', 'name')->ignore($ignorarId)],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'different:time_start'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'Ya existe un turno con ese nombre.',
            'time_start.date_format' => 'La hora de inicio debe tener el formato HH:MM (24 horas).',
            'time_end.date_format' => 'La hora de término debe tener el formato HH:MM (24 horas).',
            'time_end.different' => 'La hora de término debe ser distinta a la de inicio.',
        ]);

        $this->validarDuracion($datos['time_start'], $datos['time_end']);

        // Un turno que se cruza con otro haría ambiguo qué turno corresponde a cada marcación.
        $this->validarSinCruces($datos['time_start'], $datos['time_end'], $ignorarId);

        return $datos;
    }

    /** Un turno no puede ser ridículamente corto ni durar más de lo permitido (se cuenta hasta el día siguiente si cruza la medianoche). */
    private function validarDuracion(string $inicio, string $fin): void
    {
        $minutos = fn (string $hora) => ((int) substr($hora, 0, 2)) * 60 + (int) substr($hora, 3, 2);
        $duracion = $minutos($fin) - $minutos($inicio);
        if ($duracion <= 0) {
            $duracion += 24 * 60;
        }

        $min = (int) config('proyecto.turnos.duracion_minima_horas');
        $max = (int) config('proyecto.turnos.duracion_maxima_horas');

        if ($duracion < $min * 60) {
            throw ValidationException::withMessages(['time_end' => "El turno debe durar al menos {$min} hora(s)."]);
        }

        if ($duracion > $max * 60) {
            throw ValidationException::withMessages(['time_end' => "El turno no puede durar más de {$max} horas."]);
        }
    }

    /** Dos turnos no pueden ocupar las mismas horas del día (los nocturnos se parten en dos tramos). */
    private function validarSinCruces(string $inicio, string $fin, ?int $ignorarId): void
    {
        $tramos = fn (string $i, string $f) => $f > $i ? [[$i, $f]] : [[$i, '24:00'], ['00:00', $f]];

        $otros = Turno::when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))->get();

        foreach ($otros as $otro) {
            foreach ($tramos($inicio, $fin) as [$a1, $a2]) {
                foreach ($tramos(substr($otro->time_start, 0, 5), substr($otro->time_end, 0, 5)) as [$b1, $b2]) {
                    if ($a1 < $b2 && $b1 < $a2) {
                        throw ValidationException::withMessages([
                            'time_start' => "Las horas se cruzan con el turno «{$otro->name}» ({$otro->rango}).",
                        ]);
                    }
                }
            }
        }
    }

    private function fila(Turno $turno): array
    {
        return [
            'id' => $turno->id,
            'nombre' => $turno->name,
            'descripcion' => $turno->description,
            'entrada' => substr($turno->time_start, 0, 5),
            'salida' => substr($turno->time_end, 0, 5),
            'nocturno' => $turno->cruzaMedianoche(),
            'asistencias' => $turno->asistencias_count ?? $turno->asistencias()->count(),
            'creado' => $turno->created_at?->format('d/m/Y H:i'),
            'actualizado' => $turno->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
