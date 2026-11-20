<?php

use App\Http\Controllers\Api\CiudadanoController;
use App\Http\Controllers\Api\Conductor\AccesoController;
use App\Http\Controllers\Api\Conductor\ProgramacionController;
use App\Http\Controllers\Api\Conductor\RecorridoController;
use App\Http\Controllers\Api\ConsultaRecoleccionController;
use App\Http\Middleware\SoloCiudadano;
use Illuminate\Support\Facades\Route;

/*
| API de la app móvil del conductor (Avance 6). Todas las rutas quedan bajo /api/conductor.
| Autenticación: token de Sanctum en la cabecera «Authorization: Bearer <token>».
*/

Route::get('v1/recoleccion', [ConsultaRecoleccionController::class, 'index'])->middleware('throttle:60,1')->name('api.recoleccion.index');
// El limitador de Laravel usa MySQL en despliegue. El frontal limita esta ruta antes de llegar a PHP.
Route::get('v1/salud', [ConsultaRecoleccionController::class, 'salud'])->name('api.salud');

Route::prefix('v1/ciudadano')->name('api.ciudadano.')->controller(CiudadanoController::class)->group(function () {
    Route::post('registro', 'registrar')->middleware('throttle:6,1,ciudadano-registro')->name('registro');
    Route::post('login', 'login')->middleware('throttle:6,1,ciudadano-login')->name('login');
    Route::get('zonas', 'zonas')->middleware('throttle:60,1')->name('zonas');
    Route::post('zonas/ubicar', 'ubicar')->middleware('throttle:30,1')->name('ubicar');
    Route::middleware(['auth:sanctum', SoloCiudadano::class, 'throttle:120,1'])->group(function () {
        Route::get('perfil', 'perfil')->name('perfil');
        Route::post('logout', 'logout')->name('logout');
        Route::post('zona', 'cambiarZona')->name('zona');
        Route::get('programaciones', 'programaciones')->name('programaciones');
        Route::get('programaciones/{programacion}', 'detalle')->whereNumber('programacion')->name('detalle');
        Route::post('programaciones/{programacion}/valoracion', 'calificar')->whereNumber('programacion')->name('valoracion');
        Route::get('historial', 'historial')->name('historial');
    });
});

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
