<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\TipoPersonal;
use Illuminate\Http\Request;
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
        $datos['profile_photo_path'] = $request->hasFile('photo')
            ? $this->guardarArchivo($request->file('photo'), 'employee_photos')
            : null;
        unset($datos['photo']);

        $persona = Personal::create($datos);

        return $this->exito('Personal registrado', 'admin.personal.index', ['persona' => $this->fila($persona->load('tipo'))]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.personal.edit', $this->catalogos() + ['persona' => Personal::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $persona = Personal::findOrFail($id);
        $datos = $this->validado($request, $persona);

        // Si el campo de clave llega vacío se conserva la actual.
        if (blank($datos['password'] ?? null)) {
            unset($datos['password']);
        }

        if ($request->hasFile('photo')) {
            $this->borrarArchivo($persona->profile_photo_path);
            $datos['profile_photo_path'] = $this->guardarArchivo($request->file('photo'), 'employee_photos');
        }

        unset($datos['photo']);
        $persona->update($datos);

        return $this->exito('Personal actualizado', 'admin.personal.index', [
            'persona' => $this->fila($persona->fresh(['tipo', 'contratoVigente'])),
        ]);
    }

    public function destroy(string $id)
    {
        $persona = Personal::withCount(['contratos', 'asistencias'])->findOrFail($id);

        if ($persona->contratos_count > 0 || $persona->asistencias_count > 0) {
            return $this->rechazo('No se puede eliminar: tiene contratos o asistencias. Márquelo como inactivo.', 'admin.personal.index');
        }

        $this->borrarArchivo($persona->profile_photo_path);
        $persona->delete();

        return $this->exito('Personal eliminado', 'admin.personal.index');
    }

    private function catalogos(): array
    {
        return [
            'tipos' => TipoPersonal::orderByDesc('is_system')->orderBy('name')->get(['id', 'name']),
            'idConductor' => TipoPersonal::conductor()?->id,
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
            'name' => ['required', 'string', 'max:100', "regex:/^[\pL\s'.\-]+$/u"],
            'last_name' => ['required', 'string', 'max:100', "regex:/^[\pL\s'.\-]+$/u"],
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
            'creado' => $persona->created_at?->format('d/m/Y H:i'),
        ];
    }
}
