<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Bloqueo;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Cubre perfil y Livewire, que no pasan por las rutas del panel. */
class ProtegeCuentaActiva
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user();
        if (! $usuario || $request->is('admin', 'admin/*', 'logout')) {
            return $next($request);
        }
        $vigente = User::find($usuario->id);
        abort_unless($vigente && $vigente->status && $vigente->usertype_id === null, 403);
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $usuario) {
            Bloqueo::tomar('administradores');
            $vigente = User::whereKey($usuario->id)->lockForUpdate()->first();
            abort_unless($vigente && $vigente->status && $vigente->usertype_id === null, 403);
            Auth::setUser($vigente);
            $respuesta = $next($request);
            if ($respuesta->getStatusCode() >= 400) {
                throw new HttpResponseException($respuesta);
            }

            return $respuesta;
        });
    }
}
