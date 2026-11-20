<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El panel web funciona solo con la sesión del navegador. Los tokens (como el de la app del conductor)
 * son para /api: aquí se rechazan, para que un token de conductor no abra ninguna pantalla del panel.
 */
class SinTokenEnElPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            $mensaje = 'El panel no acepta tokens de acceso. Ingrese desde el navegador.';

            return $request->expectsJson()
                ? response()->json(['message' => $mensaje], 403)
                : abort(403, $mensaje);
        }

        return $next($request);
    }
}
