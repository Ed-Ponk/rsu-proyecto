<?php

use App\Http\Controllers\Api\Conductor\AccesoController;
use App\Http\Controllers\Api\Conductor\ProgramacionController;
use App\Http\Controllers\Api\Conductor\RecorridoController;
use Illuminate\Support\Facades\Route;

/*
| API de la app móvil del conductor (Avance 6). Todas las rutas quedan bajo /api/conductor.
| Autenticación: token de Sanctum en la cabecera «Authorization: Bearer <token>».
*/

Route::prefix('conductor')->name('api.conductor.')->group(function () {
    Route::post('login', [AccesoController::class, 'login'])->middleware('throttle:6,1')->name('login');

    Route::middleware(['auth:sanctum', 'conductor', 'throttle:120,1'])->group(function () {
        Route::get('perfil', [AccesoController::class, 'perfil'])->name('perfil');
        Route::post('logout', [AccesoController::class, 'logout'])->name('logout');

        Route::get('programaciones', [ProgramacionController::class, 'index'])->name('programaciones.index');
        Route::get('programaciones/hoy', [ProgramacionController::class, 'hoy'])->name('programaciones.hoy');
        Route::get('programaciones/{programacion}', [ProgramacionController::class, 'show'])->whereNumber('programacion')->name('programaciones.show');

        Route::post('programaciones/{programacion}/recorrido', [RecorridoController::class, 'iniciar'])->whereNumber('programacion')->name('recorridos.iniciar');
        Route::get('recorridos', [RecorridoController::class, 'index'])->name('recorridos.index');
        Route::get('recorridos/actual', [RecorridoController::class, 'actual'])->name('recorridos.actual');
        Route::get('recorridos/{recorrido}', [RecorridoController::class, 'show'])->whereNumber('recorrido')->name('recorridos.show');
        Route::post('recorridos/{recorrido}/finalizar', [RecorridoController::class, 'finalizar'])->whereNumber('recorrido')->name('recorridos.finalizar');
    });

    // El GPS manda un punto cada 5 s (o un lote si estuvo sin señal): límite propio por minuto.
    Route::middleware(['auth:sanctum', 'conductor', 'throttle:60,1'])
        ->post('recorridos/{recorrido}/puntos', [RecorridoController::class, 'puntos'])->whereNumber('recorrido')->name('recorridos.puntos');
});
