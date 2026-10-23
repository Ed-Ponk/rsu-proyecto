<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PersonalRequest;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\Vacacion;
use App\Services\ReglasVacacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PersonalController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->routeIs('*.datos') && ! request()->ajax()) {
            return view('admin.personal.index');
        }

        $filas = Personal::with(['tipo', 'contratoVigente'])
            ->withCount('contratos')
            ->orderBy('last_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Personal $persona) => $this->fila($persona));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.personal.create', $this->catalogos());
    }

    public function store(PersonalRequest $request)
    {
        $datos = $this->validado($request);
        unset($datos['photo']);

        $persona = $this->conArchivoNuevo($request->file('photo'), 'employee_photos', fn (?string $foto) => Personal::create($datos + ['profile_photo_path' => $foto]));

        return $this->exito('Personal registrado', 'admin.personal.index', ['persona' => $this->fila($persona->load('tipo'))]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    /**
     * Buscador de personal activo por DNI, nombres o apellidos (cada palabra debe coincidir).
     * El contexto decide qué dato de contrato se informa:
     *   - contrato:   si ya tiene uno activo (no puede tener dos activos a la vez);
     *   - asistencia: si tiene uno vigente hoy (sin él no puede marcar).
     */
    public function buscar(Request $request)
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:60'],
            'contexto' => ['required', Rule::in(['contrato', 'asistencia', 'vacaciones'])],
        ]);

        // Sin tildes ni mayúsculas: "perez" encuentra a "Pérez" (LIKE de SQLite no ignora las tildes).
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii((string) $texto));
        $palabras = array_filter(preg_split('/\s+/', $normalizar(trim((string) ($datos['q'] ?? '')))));
        $paraAsistencia = $datos['contexto'] === 'asistencia';
        $paraVacaciones = $datos['contexto'] === 'vacaciones';
        $anio = today()->year;

        // El personal activo es un conjunto acotado: se filtra en PHP para poder comparar sin tildes.
        $personas = Personal::with('tipo')
            ->activos()
            ->withExists([
                'contratos as con_contrato' => fn ($c) => match (true) {
                    $paraVacaciones => $c->vigente()->whereIn('type', ReglasVacacion::CONTRATOS_CON_DERECHO),
                    $paraAsistencia => $c->vigente(),
                    default => $c->noVencido(),
                },
            ])
            ->orderBy('last_name')->orderBy('name')
            ->get()
            ->filter(function (Personal $p) use ($palabras, $normalizar) {
                $nombre = $normalizar("{$p->name} {$p->last_name}");

                foreach ($palabras as $palabra) {
                    if (! str_starts_with((string) $p->dni, $palabra) && ! str_contains($nombre, $palabra)) {
                        return false;
                    }
                }

                return true;
            })
            ->take(8)
            ->values();

        return response()->json(['data' => $personas->map(function (Personal $p) use ($paraAsistencia, $paraVacaciones, $anio) {
            // En la lista solo se marca lo relevante; en la tarjeta elegida siempre se informa el estado.
            [$enLista, $elegido] = match (true) {
                $paraVacaciones => $this->insigniasDeVacaciones($p, $anio),
                default => $this->insigniasDeContrato($p, $paraAsistencia),
            };

            return [
                'id' => $p->id,
                'version' => (int) $p->version,
                'titulo' => $p->nombre_completo,
                'detalle' => "DNI {$p->dni} · ".($p->tipo?->name ?? 'Sin función'),
                'foto' => $p->url_foto,
                'iniciales' => mb_strtoupper(mb_substr($p->name, 0, 1).mb_substr((string) $p->last_name, 0, 1)),
                'insignia' => $enLista,
                'insignia_elegido' => $elegido,
            ];
        })]);
    }

    /** @return array{0: ?array, 1: array} insignia de la lista y de la tarjeta elegida */
    private function insigniasDeContrato(Personal $p, bool $paraAsistencia): array
    {
        return $paraAsistencia
                ? ($p->con_contrato
                    ? [null, ['texto' => 'Contrato vigente', 'color' => 'verde']]
                    : [['texto' => 'Sin contrato vigente', 'color' => 'rojo'], ['texto' => 'Sin contrato vigente', 'color' => 'rojo']])
                : ($p->con_contrato
                    ? [['texto' => 'Contrato activo', 'color' => 'ambar'], ['texto' => 'Contrato activo', 'color' => 'ambar']]
                    : [null, ['texto' => 'Sin contrato activo', 'color' => 'verde']]);
    }

    /** Vacaciones: solo el personal nombrado o permanente puede pedirlas; se informa cuántos días le quedan. */
    private function insigniasDeVacaciones(Personal $p, int $anio): array
    {
        if (! $p->con_contrato) {
            $sin = ['texto' => 'Sin contrato nombrado/permanente', 'color' => 'rojo'];

            return [$sin, $sin];
        }

        $dias = Vacacion::diasDisponibles($p->id, $anio);
        $elegido = ['texto' => "{$dias} día(s) disponibles en {$anio}", 'color' => $dias > 0 ? 'verde' : 'ambar'];

        return [null, $elegido];
    }

    /** Historial de contratos de una persona (se abre en la ventana emergente del listado). */
    public function contratos(string $id)
    {
        Contrato::cerrarVencidos();

        $persona = Personal::with(['tipo', 'contratos'])->findOrFail($id);

        return view('admin.personal.contratos', [
            'persona' => $persona,
            'vigente' => $persona->contratos->first(fn (Contrato $c) => $c->estado === 'Vigente'),
            'tipos' => Contrato::TIPOS,
        ]);
    }

    public function edit(string $id)
    {
        $persona = Personal::findOrFail($id);
        $tipo = TipoPersonal::withCount('personal')->find((int) old('usertype_id', $persona->usertype_id));

        return view('admin.personal.edit', [
            'persona' => $persona,
            'tipoElegido' => $tipo ? TipoPersonalController::itemDeBuscador($tipo) : null,
        ] + $this->catalogos());
    }

    public function update(PersonalRequest $request, string $id)
    {
        $persona = Personal::findOrFail($id);
        $datos = $this->validado($request, $persona);

        // Si el campo de clave llega vacío se conserva la actual.
        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        unset($datos['photo']);

        $this->conArchivoNuevo($request->file('photo'), 'employee_photos', function (?string $foto) use ($persona, $datos) {
            $persona->update($foto ? $datos + ['profile_photo_path' => $foto] : $datos);
        }, $persona->profile_photo_path);

        return $this->exito('Personal actualizado', 'admin.personal.index', [
            'persona' => $this->fila($persona->fresh(['tipo', 'contratoVigente'])),
        ]);
    }

    public function destroy(string $id)
    {
        $persona = Personal::withCount(['contratos', 'asistencias', 'vacaciones'])->findOrFail($id);

        if ($persona->contratos_count > 0 || $persona->asistencias_count > 0 || $persona->vacaciones_count > 0) {
            return $this->rechazo('No se puede eliminar: tiene contratos, asistencias o vacaciones registradas. Márquelo como inactivo.', 'admin.personal.index');
        }

        $persona->delete();
        DB::afterCommit(fn () => $this->borrarArchivo($persona->profile_photo_path));

        return $this->exito('Personal eliminado', 'admin.personal.index');
    }

    private function catalogos(): array
    {
        return [
            'idConductor' => TipoPersonal::conductor()?->id,
            'tipoElegido' => null,
        ];
    }

    private function validado(PersonalRequest $request, ?Personal $actual = null): array
    {
        return $request->datos($actual);
    }

    private function fila(Personal $persona): array
    {
        return [
            'id' => $persona->id,
            'version' => (int) $persona->version,
            'foto' => $persona->url_foto,
            'iniciales' => mb_strtoupper(mb_substr($persona->name, 0, 1).mb_substr((string) $persona->last_name, 0, 1)),
            'dni' => $persona->dni,
            'nombres' => $persona->name,
            'apellidos' => $persona->last_name,
            'nombre_completo' => $persona->nombre_completo,
            'correo' => $persona->email,
            'funcion' => $persona->tipo?->name,
            'activo' => $persona->status,
            'con_contrato' => $persona->relationLoaded('contratoVigente')
                ? $persona->contratoVigente !== null
                : $persona->contratoVigente()->exists(),
            'contratos' => $persona->contratos_count ?? $persona->contratos()->count(),
            'creado' => $persona->created_at?->format('d/m/Y H:i'),
        ];
    }
}
