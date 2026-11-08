<?php

namespace App\Http\Middleware;

use App\Support\CatalogoPermisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege todo el panel: cada ruta exige el permiso que indica CatalogoPermisos (por ejemplo, admin.colores.store → colors.create).
 * Los administradores pasan siempre; los operadores solo entran a lo que se les concedió.
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next): Response
    {
        $exigido = CatalogoPermisos::paraRuta($request->route()?->getName());

        if ($exigido === null || $this->autorizado($request, $exigido)) {
            return $next($request);
        }

        $mensaje = 'No tiene permiso para realizar esta acción. Solicítelo a un administrador.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $mensaje, 'mensaje' => $mensaje], 403);
        }

        abort(403, $mensaje);
    }

    private function autorizado(Request $request, string|array $exigido): bool
    {
        $usuario = $request->user();

        if (! $usuario) {
            return false;
        }

        if ($exigido === CatalogoPermisos::CUALQUIERA) {
            return $usuario->esAdministrador() || $usuario->permisosConcedidos() !== [];
        }

        return $usuario->tieneAlgunPermiso((array) $exigido);
    }
}
