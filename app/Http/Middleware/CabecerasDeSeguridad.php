<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas del panel: evita que otra página incruste el sistema
 * (clickjacking), que el navegador "adivine" tipos de archivo y que se filtre la dirección completa al salir.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        // Sin script-src a propósito: el panel usa scripts en línea; estas directivas cierran lo demás.
        $respuesta->headers->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $respuesta->headers->remove('X-Powered-By');

        // Una misma URL (p. ej. /admin/turnos) devuelve la página o, pedida por la tabla con AJAX, sus datos en JSON.
        // Sin esto, el botón «Atrás» del navegador puede mostrar el JSON guardado en caché en lugar de la página.
        $respuesta->setVary(['X-Requested-With', 'Accept'], false);
        if ($request->ajax() || $request->wantsJson()) {
            $respuesta->headers->set('Cache-Control', 'no-store, private');
        }

        return $respuesta;
    }
}
