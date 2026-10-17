<?php

use App\Http\Controllers\Admin\AsistenciaController;
use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\ContratoController;
use App\Http\Controllers\Admin\InicioController;
use App\Http\Controllers\Admin\MarcaController;
use App\Http\Controllers\Admin\ModeloController;
use App\Http\Controllers\Admin\PersonalController;
use App\Http\Controllers\Admin\TipoPersonalController;
use App\Http\Controllers\Admin\TipoVehiculoController;
use App\Http\Controllers\Admin\VehiculoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

$protegidas = ['auth:sanctum', config('jetstream.auth_session'), 'verified'];

Route::middleware($protegidas)->get('/dashboard', fn () => redirect('/admin'))->name('dashboard');

Route::middleware($protegidas)->prefix('admin')->group(function () {
    Route::get('/', [InicioController::class, 'index'])->name('admin.inicio');

    // ---- Gestión de vehículos
    Route::resource('colores', ColorController::class)->names('admin.colores')->parameters(['colores' => 'color']);
    Route::resource('marcas', MarcaController::class)->names('admin.marcas');
    Route::resource('modelos', ModeloController::class)->names('admin.modelos');
    Route::resource('tipos-vehiculo', TipoVehiculoController::class)->names('admin.tiposvehiculo')->parameters(['tipos-vehiculo' => 'tipo']);

    Route::get('vehiculos/{vehiculo}/imagenes', [VehiculoController::class, 'imagenes'])->name('admin.vehiculos.imagenes');
    Route::post('vehiculos/{vehiculo}/imagenes', [VehiculoController::class, 'guardarImagenes'])->name('admin.vehiculos.imagenes.guardar');
    Route::resource('vehiculos', VehiculoController::class)->names('admin.vehiculos');

    // ---- Gestión de personal
    Route::resource('tipos-personal', TipoPersonalController::class)->names('admin.tipospersonal')->parameters(['tipos-personal' => 'tipo']);
    Route::resource('personal', PersonalController::class)->names('admin.personal')->parameters(['personal' => 'persona']);

    Route::patch('contratos/{contrato}/estado', [ContratoController::class, 'cambiarEstado'])->name('admin.contratos.estado');
    Route::resource('contratos', ContratoController::class)->except('destroy')->names('admin.contratos');

    // La ruta fija va antes del resource para que no la capture {asistencia}.
    Route::get('asistencias/vista-previa', [AsistenciaController::class, 'vistaPrevia'])->name('admin.asistencias.vistaprevia');
    Route::resource('asistencias', AsistenciaController::class)->except('show')->names('admin.asistencias');
});
