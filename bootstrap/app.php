<?php

use App\Http\Middleware\CabecerasDeSeguridad;
use App\Http\Middleware\ProtegeCuentaActiva;
use Illuminate\Database\QueryException;
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
        $middleware->appendToGroup('web', ProtegeCuentaActiva::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $error, Request $request) {
            $estado = $error->errorInfo[0] ?? null;
            if (! in_array($estado, ['23000', '23505', '23503'], true)) {
                return null;
            }
            $mensaje = 'No se pudo guardar: un dato ya existe o el registro tiene dependencias. Recargue y revise los datos.';
            if ($request->expectsJson()) {
                return response()->json(['mensaje' => $mensaje], 422);
            }

            return back()->withInput()->with('error', $mensaje);
        });
    })->create();
