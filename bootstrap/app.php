<?php

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Support\MensajesDeBase;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/admin');
        $middleware->appendToGroup('web', CabecerasDeSeguridad::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Dos personas guardan lo mismo en el mismo instante: la validación pasa en ambas y la base de datos rechaza la segunda.
        // En vez de un error 500, esa persona recibe un mensaje claro.
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            $mensaje = MensajesDeBase::paraDuplicado($e->getMessage());

            return $request->expectsJson()
                ? response()->json(['message' => $mensaje, 'mensaje' => $mensaje, 'errors' => (object) []], 422)
                : back()->withInput()->with('error', $mensaje);
        });

        // Dos transacciones se cruzaron (deadlock) y ya se agotaron los reintentos: se pide repetir, no se muestra un error técnico.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! MensajesDeBase::esInterbloqueo($e)) {
                return null;
            }

            $mensaje = 'El sistema estaba ocupado con otra operación al mismo tiempo. Inténtelo de nuevo.';

            return $request->expectsJson()
                ? response()->json(['message' => $mensaje, 'mensaje' => $mensaje], 409)
                : back()->withInput()->with('error', $mensaje);
        });
    })->create();
