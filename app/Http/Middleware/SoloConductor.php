<?php

namespace App\Http\Middleware;

use App\Models\Personal;
use App\Models\TipoPersonal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API móvil es solo para conductores activos que entraron desde la app (token con la habilidad «conductor»).
 * Deja al conductor en $request->attributes->get('conductor').
 */
class SoloConductor
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->tokenCan('conductor')) {
            return $this->rechazo('Esta sección es solo para conductores.');
        }

        $conductor = Personal::find($usuario->id);

        if (! $conductor || ! $conductor->status || (int) $conductor->usertype_id !== (int) TipoPersonal::conductor()?->id) {
            // Lo dieron de baja o le cambiaron la función después de iniciar sesión: el token deja de servir.
            $usuario->currentAccessToken()?->delete();

            return $this->rechazo('Su cuenta ya no está habilitada como conductor. Consulte con el administrador.', 401);
        }

        $request->attributes->set('conductor', $conductor);

        return $next($request);
    }

    private function rechazo(string $mensaje, int $codigo = 403): Response
    {
        return response()->json(['message' => $mensaje], $codigo);
    }
}
