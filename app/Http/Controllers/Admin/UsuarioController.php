<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UsuarioRequest;
use App\Models\User;
use App\Services\SeguridadAdministrativa;
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

    public function store(UsuarioRequest $request)
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

    public function update(UsuarioRequest $request, string $id)
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

        app(SeguridadAdministrativa::class)->eliminar($usuario);

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

    private function validado(UsuarioRequest $request, ?User $actual = null): array
    {
        return $request->datos($actual);
    }

    private function fila(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'version' => (int) $usuario->version,
            'nombre' => $usuario->name,
            'correo' => $usuario->email,
            'activo' => (bool) $usuario->status,
            'dos_pasos' => $usuario->two_factor_confirmed_at !== null,
            'actual' => $usuario->is(auth()->user()),
            'creado' => $usuario->created_at?->format('d/m/Y H:i'),
        ];
    }
}
