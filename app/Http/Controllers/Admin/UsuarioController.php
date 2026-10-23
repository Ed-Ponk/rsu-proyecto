<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Usuarios del sistema (administradores). El personal operativo (conductores y ayudantes) no inicia sesión y no aparece aquí. */
class UsuarioController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->routeIs('*.datos') && ! request()->ajax()) {
            return view('admin.usuarios.index');
        }

        $filas = $this->administradores()->orderBy('name')->get()->map(fn (User $u) => $this->fila($u));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.usuarios.create');
    }

    public function store(Request $request)
    {
        $datos = $this->validado($request);

        $usuario = new User(['name' => $datos['name'], 'email' => $datos['email'], 'password' => $datos['password']]);
        $usuario->forceFill(['status' => $datos['status'], 'email_verified_at' => now()])->save();

        return $this->exito('Usuario registrado', 'admin.usuarios.index', ['usuario' => $this->fila($usuario)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.usuarios.edit', ['usuario' => $this->administradores()->findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $usuario = $this->administradores()->findOrFail($id);
        $datos = $this->validado($request, $usuario);

        if (! $datos['status'] && ($motivo = $this->motivoParaNoDesactivar($usuario))) {
            throw ValidationException::withMessages(['status' => $motivo]);
        }

        $usuario->forceFill(['name' => $datos['name'], 'email' => $datos['email'], 'status' => $datos['status']]);

        if (filled($datos['password'] ?? null)) {
            $usuario->password = $datos['password'];
        }

        $usuario->save();

        return $this->exito('Usuario actualizado', 'admin.usuarios.index', ['usuario' => $this->fila($usuario->fresh())]);
    }

    public function destroy(string $id)
    {
        $usuario = $this->administradores()->findOrFail($id);

        if ($motivo = $this->motivoParaNoEliminar($usuario)) {
            return $this->rechazo($motivo, 'admin.usuarios.index');
        }

        $usuario->tokens()->delete();
        $usuario->delete();

        return $this->exito('Usuario eliminado', 'admin.usuarios.index');
    }

    private function administradores()
    {
        return User::whereNull('usertype_id');
    }

    /** Siempre debe quedar al menos un administrador activo, y nadie puede quitarse el acceso a sí mismo. */
    private function motivoParaNoDesactivar(User $usuario): ?string
    {
        if ($usuario->is(auth()->user())) {
            return 'No puede desactivar su propia cuenta.';
        }

        $otrosActivos = $this->administradores()->where('status', true)->whereKeyNot($usuario->id)->exists();

        return $otrosActivos ? null : 'Debe quedar al menos un administrador activo.';
    }

    private function motivoParaNoEliminar(User $usuario): ?string
    {
        if ($usuario->is(auth()->user())) {
            return 'No puede eliminar su propia cuenta desde aquí (use "Mi perfil").';
        }

        return $this->administradores()->where('status', true)->whereKeyNot($usuario->id)->exists()
            ? null
            : 'No se puede eliminar: debe quedar al menos un administrador activo.';
    }

    private function validado(Request $request, ?User $actual = null): array
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email'))), 'status' => $request->boolean('status')]);

        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($actual?->id)],
            'password' => [$actual ? 'nullable' : 'required', 'string', 'max:72', 'confirmed', Password::defaults()],
            'status' => ['boolean'],
        ], [
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'name.min' => 'El nombre debe tener al menos 3 caracteres.',
        ]);
    }

    private function fila(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'correo' => $usuario->email,
            'activo' => (bool) $usuario->status,
            'dos_pasos' => $usuario->two_factor_confirmed_at !== null,
            'actual' => $usuario->is(auth()->user()),
            'creado' => $usuario->created_at?->format('d/m/Y H:i'),
        ];
    }
}
