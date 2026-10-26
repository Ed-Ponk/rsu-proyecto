<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\Vacacion;
use App\Services\ReglasVacacion;
use App\Support\UsoEnProgramacion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
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

    public function store(Request $request)
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
            'contexto' => ['required', Rule::in(['contrato', 'asistencia', 'vacaciones', 'conductor', 'ayudante'])],
        ]);

        // Sin tildes ni mayúsculas: "perez" encuentra a "Pérez" (LIKE de SQLite no ignora las tildes).
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii((string) $texto));
        $palabras = array_filter(preg_split('/\s+/', $normalizar(trim((string) ($datos['q'] ?? '')))));
        $paraAsistencia = $datos['contexto'] === 'asistencia';
        $paraVacaciones = $datos['contexto'] === 'vacaciones';
        $paraRuta = in_array($datos['contexto'], ['conductor', 'ayudante'], true);
        $funcion = $datos['contexto'] === 'conductor' ? TipoPersonal::conductor() : TipoPersonal::ayudante();
        $anio = today()->year;

        // El personal activo es un conjunto acotado: se filtra en PHP para poder comparar sin tildes.
        $personas = Personal::with('tipo')
            ->activos()
            ->when($paraRuta, fn ($q) => $q->where('users.usertype_id', $funcion?->id ?? 0))
            ->withExists([
                'contratos as con_contrato' => fn ($c) => match (true) {
                    $paraVacaciones => $c->vigente()->whereIn('type', ReglasVacacion::CONTRATOS_CON_DERECHO),
                    $paraAsistencia, $paraRuta => $c->vigente(),
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

        return response()->json(['data' => $personas->map(function (Personal $p) use ($paraAsistencia, $paraVacaciones, $paraRuta, $anio) {
            // En la lista solo se marca lo relevante; en la tarjeta elegida siempre se informa el estado.
            [$enLista, $elegido] = match (true) {
                $paraVacaciones => $this->insigniasDeVacaciones($p, $anio),
                $paraRuta => $this->insigniasDeRuta($p),
                default => $this->insigniasDeContrato($p, $paraAsistencia),
            };

            return [
                'id' => $p->id,
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

    /** Conductores y ayudantes de las programaciones: se informa si hoy tienen contrato vigente. */
    private function insigniasDeRuta(Personal $p): array
    {
        $insignia = $p->con_contrato
            ? ['texto' => 'Disponible', 'color' => 'verde']
            : ['texto' => 'Sin contrato vigente', 'color' => 'rojo'];

        return [$p->con_contrato ? null : $insignia, $insignia];
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

    public function update(Request $request, string $id)
    {
        $persona = Personal::findOrFail($id);
        $datos = $this->validado($request, $persona);
        UsoEnProgramacion::exigirPersonaEditable($persona, $datos);

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

        if ($uso = UsoEnProgramacion::dePersona($persona->id)) {
            return $this->rechazo("No se puede eliminar: figura en {$uso}. Márquelo como inactivo.", 'admin.personal.index');
        }

        $this->borrarArchivo($persona->profile_photo_path);
        $persona->delete();

        return $this->exito('Personal eliminado', 'admin.personal.index');
    }

    private function catalogos(): array
    {
        return [
            'idConductor' => TipoPersonal::conductor()?->id,
            'tipoElegido' => null,
        ];
    }

    private function validado(Request $request, ?Personal $actual = null): array
    {
        $request->merge([
            'dni' => trim((string) $request->input('dni')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'license' => strtoupper(trim((string) $request->input('license'))) ?: null,
            'phone' => preg_replace('/\s+/', '', (string) $request->input('phone')) ?: null,
            'status' => $request->boolean('status'),
        ]);

        $ignorarId = $actual?->id;
        $esConductor = (int) $request->input('usertype_id') === (int) TipoPersonal::conductor()?->id;
        $edadMinima = (int) config('proyecto.personal.edad_minima');

        $datos = $request->validate([
            'dni' => ['required', 'digits:8', Rule::unique('users', 'dni')->ignore($ignorarId)],
            'usertype_id' => ['required', 'exists:usertypes,id'],
            'name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\pL\s'.\-]+$/u"],
            'last_name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\pL\s'.\-]+$/u"],
            'birthdate' => ['required', 'date', 'after:1940-01-01', 'before_or_equal:'.now()->subYears($edadMinima)->toDateString()],
            'phone' => ['nullable', 'regex:/^9\d{8}$/'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($ignorarId)],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'license' => [Rule::requiredIf($esConductor), 'nullable', 'regex:'.Personal::FORMATO_LICENCIA, Rule::unique('users', 'license')->ignore($ignorarId)],
            'password' => [$actual ? 'nullable' : 'required', 'string', 'min:6', 'max:60'],
            'status' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'dni.digits' => 'El DNI debe tener exactamente 8 dígitos.',
            'dni.unique' => 'Ya hay personal registrado con ese DNI.',
            'usertype_id.required' => 'Seleccione el tipo de personal.',
            'name.regex' => 'Los nombres solo pueden tener letras y espacios.',
            'name.min' => 'Escriba el nombre completo (al menos 2 letras).',
            'last_name.min' => 'Escriba el apellido completo (al menos 2 letras).',
            'last_name.regex' => 'Los apellidos solo pueden tener letras y espacios.',
            'birthdate.before_or_equal' => "La persona debe ser mayor de {$edadMinima} años.",
            'birthdate.after' => 'La fecha de nacimiento no es válida.',
            'phone.regex' => 'El teléfono tiene 9 dígitos y empieza con 9 (ej. 987654321).',
            'email.unique' => 'Ya hay personal registrado con ese correo.',
            'address.min' => 'La dirección debe tener al menos 10 caracteres.',
            'license.required' => 'La licencia de conducir es obligatoria para los conductores.',
            'license.regex' => 'La licencia es una letra seguida de 8 dígitos (ej. Q12345678).',
            'license.unique' => 'Esa licencia ya está registrada en otra persona.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'photo.image' => 'La foto debe ser una imagen válida.',
            'photo.max' => 'La foto no puede pesar más de 2 MB.',
        ]);

        // La licencia solo existe para los conductores.
        if (! $esConductor) {
            $datos['license'] = null;
        }

        // No se puede inactivar a alguien mientras tenga un contrato vigente.
        if ($actual && ! $datos['status'] && $actual->contratoVigente()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Tiene un contrato vigente. Desactive primero el contrato para poder inactivar a la persona.',
            ]);
        }

        return $datos;
    }

    private function fila(Personal $persona): array
    {
        return [
            'id' => $persona->id,
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
