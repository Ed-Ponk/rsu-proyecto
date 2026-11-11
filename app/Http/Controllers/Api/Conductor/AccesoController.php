<?php

namespace App\Http\Controllers\Api\Conductor;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Models\User;
use App\Services\ServicioConductor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Ingreso a la app móvil: el conductor usa su correo y su clave de marcación (la misma que registra en el personal). */
class AccesoController extends Controller
{
    public function __construct(private ServicioConductor $servicio) {}

    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ], [], ['email' => 'correo', 'password' => 'contraseña']);

        $conductor = Personal::where('email', mb_strtolower(trim($datos['email'])))->first();

        // Mismo mensaje para correo inexistente y contraseña errada: no se revela qué correos existen.
        if (! $conductor || ! Hash::check($datos['password'], (string) $conductor->password)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        if ((int) $conductor->usertype_id !== (int) TipoPersonal::conductor()?->id) {
            throw ValidationException::withMessages(['email' => 'Esta aplicación es solo para conductores.']);
        }

        if (! $conductor->status) {
            throw ValidationException::withMessages(['email' => 'Su cuenta está inactiva. Consulte con el administrador.']);
        }

        $token = User::findOrFail($conductor->id)
            ->createToken($datos['dispositivo'] ?? 'app-conductor', ['conductor'])
            ->plainTextToken;

        return response()->json([
            'token' => $token,
            'conductor' => $this->servicio->perfil($conductor),
            'config' => ['segundos_entre_puntos' => (int) config('proyecto.recorridos.segundos_entre_puntos', 5)],
        ]);
    }

    public function perfil(Request $request): JsonResponse
    {
        return response()->json(['conductor' => $this->servicio->perfil($request->attributes->get('conductor'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
