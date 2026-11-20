<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\User;
use App\Support\CatalogoPermisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Usuarios del sistema (los que inician sesión en el panel) y sus permisos.
 * Hay dos tipos: el administrador (acceso total) y el operador (solo lo que se le conceda en «Permisos»).
 * El personal operativo (conductores y ayudantes) no inicia sesión y no aparece aquí.
 */
class UsuarioController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.usuarios.index');
        }

        $filas = $this->delSistema()
            ->withCount('permisos')
            ->orderBy('name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $u) => $this->fila($u));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.usuarios.create', ['tiposPermitidos' => $this->tiposPermitidos()]);
    }

    public function store(Request $request)
    {
        $datos = $this->validado($request);
        unset($datos['photo']);

        $usuario = $this->conArchivoNuevo($request->file('photo'), 'user_photos', function (?string $ruta) use ($datos) {
            $usuario = new User(['name' => $datos['name'], 'email' => $datos['email'], 'password' => $datos['password']]);
            $usuario->forceFill([
                'last_name' => $datos['last_name'], 'dni' => $datos['dni'], 'birthdate' => $datos['birthdate'],
                'phone' => $datos['phone'] ?? null, 'license' => $datos['license'] ?? null, 'address' => $datos['address'],
                'role' => $datos['role'], 'status' => $datos['status'], 'email_verified_at' => now(),
                'profile_photo_path' => $ruta,
            ])->save();

            return $usuario;
        });

        return $this->exito('Usuario registrado', 'admin.usuarios.index', ['usuario' => $this->fila($usuario)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        $usuario = $this->delSistema()->findOrFail($id);
        $this->autorizarSobre($usuario);

        return view('admin.usuarios.edit', ['usuario' => $usuario, 'tiposPermitidos' => $this->tiposPermitidos()]);
    }

    public function update(Request $request, string $id)
    {
        $usuario = $this->delSistema()->findOrFail($id);
        $this->autorizarSobre($usuario);
        $datos = $this->validado($request, $usuario);

        $pierdeAcceso = ! $datos['status'] || $datos['role'] !== User::ADMINISTRADOR;
        if ($usuario->esAdministrador() && $pierdeAcceso && ($motivo = $this->motivoParaNoQuitarAcceso($usuario))) {
            throw ValidationException::withMessages([$datos['status'] ? 'role' : 'status' => $motivo]);
        }

        if ($usuario->is(auth()->user()) && $datos['role'] !== $usuario->role) {
            throw ValidationException::withMessages(['role' => 'No puede cambiar su propio tipo de usuario.']);
        }

        $this->conArchivoNuevo($request->file('photo'), 'user_photos', function (?string $ruta) use ($usuario, $datos) {
            $usuario->forceFill([
                'name' => $datos['name'], 'last_name' => $datos['last_name'], 'dni' => $datos['dni'],
                'birthdate' => $datos['birthdate'], 'email' => $datos['email'], 'phone' => $datos['phone'] ?? null,
                'license' => $datos['license'] ?? null, 'address' => $datos['address'], 'role' => $datos['role'],
                'status' => $datos['status'],
            ]);

            if ($ruta) {
                $usuario->profile_photo_path = $ruta;
            }

            if (filled($datos['password'] ?? null)) {
                $usuario->password = $datos['password'];
            }

            $usuario->save();
        }, $usuario->profile_photo_path);

        return $this->exito('Usuario actualizado', 'admin.usuarios.index', ['usuario' => $this->fila($usuario->fresh()->loadCount('permisos'))]);
    }

    public function destroy(string $id)
    {
        $usuario = $this->delSistema()->findOrFail($id);
        $this->autorizarSobre($usuario);

        if ($motivo = $this->motivoParaNoEliminar($usuario)) {
            return $this->rechazo($motivo, 'admin.usuarios.index');
        }

        $this->borrarArchivo($usuario->profile_photo_path);
        $usuario->tokens()->delete();
        $usuario->delete();   // sus permisos se borran en cascada

        return $this->exito('Usuario eliminado', 'admin.usuarios.index');
    }

    /** Pantalla «Permisos de Usuario»: un cuadro por módulo con una casilla por permiso. */
    public function permisos(string $id)
    {
        $usuario = $this->delSistema()->findOrFail($id);
        $this->autorizarSobre($usuario);

        $actor = auth()->user();
        $concedidos = $usuario->esAdministrador() ? CatalogoPermisos::codigos() : $usuario->permisosConcedidos();
        $delegables = $actor->esAdministrador() ? CatalogoPermisos::codigos() : $actor->permisosConcedidos();

        return view('admin.usuarios.permisos', [
            'usuario' => $usuario,
            'modulos' => CatalogoPermisos::modulos(),
            'concedidos' => $concedidos,
            'delegables' => $delegables,
            'esAdministrador' => $usuario->esAdministrador(),
            'esPropio' => $usuario->is($actor),
            'total' => count(CatalogoPermisos::codigos()),
        ]);
    }

    public function guardarPermisos(Request $request, string $id)
    {
        $usuario = $this->delSistema()->findOrFail($id);
        $this->autorizarSobre($usuario);

        if ($usuario->esAdministrador()) {
            return $this->rechazo('Los administradores tienen acceso total: sus permisos no se modifican.', 'admin.usuarios.index');
        }

        $actor = auth()->user();

        if ($usuario->is($actor)) {
            return $this->rechazo('No puede modificar sus propios permisos.', 'admin.usuarios.index', 403);
        }

        $datos = $request->validate([
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['string', Rule::in(CatalogoPermisos::codigos())],
        ], [
            'permisos.*.in' => 'Uno de los permisos enviados no existe.',
        ]);

        $pedidos = array_values(array_unique($datos['permisos'] ?? []));

        // Quien no es administrador solo puede conceder lo que él mismo tiene, y no puede quitar lo que no puede conceder.
        if (! $actor->esAdministrador()) {
            $delegables = $actor->permisosConcedidos();
            $actuales = $usuario->permisosConcedidos();

            if (array_diff($pedidos, $delegables)) {
                return $this->rechazo('Solo puede conceder permisos que usted mismo tiene.', 'admin.usuarios.index', 403);
            }

            $pedidos = array_values(array_unique([...$pedidos, ...array_diff($actuales, $delegables)]));
        }

        DB::transaction(function () use ($usuario, $pedidos) {
            $usuario->permisos()->whereNotIn('permission', $pedidos)->delete();

            $existentes = $usuario->permisos()->pluck('permission')->all();
            $nuevos = array_diff($pedidos, $existentes);

            if ($nuevos) {
                $usuario->permisos()->createMany(array_map(fn ($codigo) => ['permission' => $codigo], array_values($nuevos)));
            }
        });

        $usuario->olvidarPermisos();

        return $this->exito('Permisos actualizados', 'admin.usuarios.index', ['total' => count($pedidos)]);
    }

    private function delSistema()
    {
        return User::whereNull('usertype_id');
    }

    /** Tipos de usuario que quien está conectado puede asignar: un operador no puede crear administradores. */
    private function tiposPermitidos(): array
    {
        $todos = [User::ADMINISTRADOR => 'Administrador', User::OPERADOR => 'Operador'];

        return auth()->user()->esAdministrador() ? $todos : [User::OPERADOR => 'Operador'];
    }

    /** Un operador no puede tocar cuentas de administradores. */
    private function autorizarSobre(User $usuario): void
    {
        abort_if(! auth()->user()->esAdministrador() && $usuario->esAdministrador(), 403, 'Solo un administrador puede gestionar cuentas de administradores.');
    }

    /** Siempre debe quedar al menos un administrador activo, y nadie puede quitarse el acceso a sí mismo. */
    private function motivoParaNoQuitarAcceso(User $usuario): ?string
    {
        if ($usuario->is(auth()->user())) {
            return 'No puede desactivar su propia cuenta ni quitarse el tipo administrador.';
        }

        return $this->hayOtroAdministradorActivo($usuario) ? null : 'Debe quedar al menos un administrador activo.';
    }

    private function motivoParaNoEliminar(User $usuario): ?string
    {
        if ($usuario->is(auth()->user())) {
            return 'No puede eliminar su propia cuenta desde aquí (use "Mi perfil").';
        }

        if ($usuario->esAdministrador() && ! $this->hayOtroAdministradorActivo($usuario)) {
            return 'No se puede eliminar: debe quedar al menos un administrador activo.';
        }

        return null;
    }

    private function hayOtroAdministradorActivo(User $excepto): bool
    {
        return $this->delSistema()->where('role', User::ADMINISTRADOR)->where('status', true)->whereKeyNot($excepto->id)->exists();
    }

    private function validado(Request $request, ?User $actual = null): array
    {
        $request->merge([
            'dni' => trim($request->texto('dni')),
            'email' => strtolower(trim($request->texto('email'))),
            'license' => strtoupper(trim($request->texto('license'))) ?: null,
            'phone' => preg_replace('/\s+/', '', $request->texto('phone')) ?: null,
            'status' => $request->boolean('status'),
        ]);

        $ignorarId = $actual?->id;
        $edadMinima = (int) config('proyecto.personal.edad_minima');

        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\pL\s'.\-]+$/u"],
            'last_name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\pL\s'.\-]+$/u"],
            'dni' => ['required', 'digits:8', Rule::unique('users', 'dni')->ignore($ignorarId)],
            'birthdate' => ['required', 'date', 'after:1940-01-01', 'before_or_equal:'.now()->subYears($edadMinima)->toDateString()],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($ignorarId)],
            'phone' => ['nullable', 'regex:/^9\d{8}$/'],
            'license' => ['nullable', 'regex:'.Personal::FORMATO_LICENCIA, Rule::unique('users', 'license')->ignore($ignorarId)],
            'role' => ['required', Rule::in(array_keys($this->tiposPermitidos()))],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'password' => [$actual ? 'nullable' : 'required', 'string', 'max:72', Password::defaults()],
            'status' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.min' => 'Escriba el nombre completo (al menos 2 letras).',
            'name.regex' => 'Los nombres solo pueden tener letras y espacios.',
            'last_name.min' => 'Escriba el apellido completo (al menos 2 letras).',
            'last_name.regex' => 'Los apellidos solo pueden tener letras y espacios.',
            'dni.digits' => 'El DNI debe tener exactamente 8 dígitos.',
            'dni.unique' => 'Ya hay una persona registrada con ese DNI.',
            'birthdate.before_or_equal' => "La persona debe ser mayor de {$edadMinima} años.",
            'birthdate.after' => 'La fecha de nacimiento no es válida.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'phone.regex' => 'El teléfono tiene 9 dígitos y empieza con 9 (ej. 987654321).',
            'license.regex' => 'La licencia es una letra seguida de 8 dígitos (ej. Q12345678).',
            'license.unique' => 'Esa licencia ya está registrada en otra persona.',
            'role.required' => 'Seleccione el tipo de usuario.',
            'role.in' => 'El tipo de usuario no es válido.',
            'address.min' => 'La dirección debe tener al menos 10 caracteres.',
            'photo.image' => 'La foto debe ser una imagen válida.',
            'photo.max' => 'La foto no puede pesar más de 2 MB.',
        ]);
    }

    private function fila(User $usuario): array
    {
        $esAdministrador = $usuario->esAdministrador();

        return [
            'id' => $usuario->id,
            'foto' => filled($usuario->profile_photo_path) ? $usuario->profile_photo_url : null,
            'iniciales' => mb_strtoupper(mb_substr((string) $usuario->name, 0, 1).mb_substr((string) $usuario->last_name, 0, 1)),
            'nombres' => $usuario->name,
            'apellidos' => $usuario->last_name,
            'nombre_completo' => trim("{$usuario->name} {$usuario->last_name}"),
            'dni' => $usuario->dni,
            'correo' => $usuario->email,
            'telefono' => $usuario->phone,
            'tipo' => $esAdministrador ? 'Administrador' : 'Operador',
            'es_administrador' => $esAdministrador,
            'activo' => (bool) $usuario->status,
            'permisos' => $esAdministrador ? count(CatalogoPermisos::codigos()) : ($usuario->permisos_count ?? $usuario->permisos()->count()),
            'actual' => $usuario->is(auth()->user()),
            'bloqueado' => ! auth()->user()->esAdministrador() && $esAdministrador,
            'actualizado' => $usuario->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
