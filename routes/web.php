<?php

use App\Http\Controllers\Admin\AsistenciaController;
use App\Http\Controllers\Admin\BitacoraController;
use App\Http\Controllers\Admin\CambioController;
use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\ContratoController;
use App\Http\Controllers\Admin\DashboardProgramacionController;
use App\Http\Controllers\Admin\FeriadoController;
use App\Http\Controllers\Admin\GrupoPersonalController;
use App\Http\Controllers\Admin\InicioController;
use App\Http\Controllers\Admin\MarcaController;
use App\Http\Controllers\Admin\ModeloController;
use App\Http\Controllers\Admin\MotivoController;
use App\Http\Controllers\Admin\PersonalController;
use App\Http\Controllers\Admin\ProgramacionController;
use App\Http\Controllers\Admin\TipoPersonalController;
use App\Http\Controllers\Admin\TipoVehiculoController;
use App\Http\Controllers\Admin\TurnoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\VacacionController;
use App\Http\Controllers\Admin\VehiculoController;
use App\Http\Controllers\Admin\ZonaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

$protegidas = ['auth:sanctum', config('jetstream.auth_session'), 'verified'];

Route::middleware($protegidas)->get('/dashboard', fn () => redirect('/admin'))->name('dashboard');

Route::middleware($protegidas)->prefix('admin')->group(function () {
    Route::get('/', [InicioController::class, 'index'])->name('admin.inicio');

    // ---- Gestión de vehículos
    Route::get('colores/buscar', [ColorController::class, 'buscar'])->name('admin.colores.buscar');
    Route::resource('colores', ColorController::class)->names('admin.colores')->parameters(['colores' => 'color']);
    // Rutas fijas antes de cada resource para que no las capture {marca} / {modelo}.
    Route::get('marcas/buscar', [MarcaController::class, 'buscar'])->name('admin.marcas.buscar');
    Route::resource('marcas', MarcaController::class)->names('admin.marcas');
    Route::get('modelos/verificar', [ModeloController::class, 'verificar'])->name('admin.modelos.verificar');
    Route::get('modelos/buscar', [ModeloController::class, 'buscar'])->name('admin.modelos.buscar');
    Route::resource('modelos', ModeloController::class)->names('admin.modelos');
    Route::get('tipos-vehiculo/buscar', [TipoVehiculoController::class, 'buscar'])->name('admin.tiposvehiculo.buscar');
    Route::resource('tipos-vehiculo', TipoVehiculoController::class)->names('admin.tiposvehiculo')->parameters(['tipos-vehiculo' => 'tipo']);

    Route::get('vehiculos/{vehiculo}/imagenes', [VehiculoController::class, 'imagenes'])->name('admin.vehiculos.imagenes');
    Route::post('vehiculos/{vehiculo}/imagenes', [VehiculoController::class, 'guardarImagenes'])->name('admin.vehiculos.imagenes.guardar');
    Route::get('vehiculos/buscar', [VehiculoController::class, 'buscar'])->name('admin.vehiculos.buscar');
    Route::resource('vehiculos', VehiculoController::class)->names('admin.vehiculos');

    // ---- Gestión de personal
    Route::get('tipos-personal/buscar', [TipoPersonalController::class, 'buscar'])->name('admin.tipospersonal.buscar');
    Route::resource('tipos-personal', TipoPersonalController::class)->names('admin.tipospersonal')->parameters(['tipos-personal' => 'tipo']);
    // Rutas fijas antes del resource para que no las capture {persona}.
    Route::get('personal/buscar', [PersonalController::class, 'buscar'])->name('admin.personal.buscar');
    Route::get('personal/{persona}/vacaciones', [VacacionController::class, 'deLaPersona'])->name('admin.personal.vacaciones');
    Route::get('personal/{persona}/contratos', [PersonalController::class, 'contratos'])->name('admin.personal.contratos');
    Route::resource('personal', PersonalController::class)->names('admin.personal')->parameters(['personal' => 'persona']);

    Route::patch('contratos/{contrato}/estado', [ContratoController::class, 'cambiarEstado'])->name('admin.contratos.estado');
    Route::resource('contratos', ContratoController::class)->except('destroy')->names('admin.contratos');

    // La ruta fija va antes del resource para que no la capture {asistencia}.
    Route::get('asistencias/vista-previa', [AsistenciaController::class, 'vistaPrevia'])->name('admin.asistencias.vistaprevia');
    Route::get('asistencias/marcacion', [AsistenciaController::class, 'marcacion'])->name('admin.asistencias.marcacion');
    Route::get('asistencias/marcacion/estado', [AsistenciaController::class, 'estadoMarcacion'])->name('admin.asistencias.marcacion.estado');
    Route::post('asistencias/marcacion', [AsistenciaController::class, 'marcar'])->name('admin.asistencias.marcar');
    Route::resource('asistencias', AsistenciaController::class)->except('show')->names('admin.asistencias');

    // Las rutas de aprobar/rechazar van antes del resource para que no las capture {vacacione}.
    Route::patch('vacaciones/{vacacion}/aprobar', [VacacionController::class, 'aprobar'])->name('admin.vacaciones.aprobar');
    Route::patch('vacaciones/{vacacion}/rechazar', [VacacionController::class, 'rechazar'])->name('admin.vacaciones.rechazar');
    Route::resource('vacaciones', VacacionController::class)->names('admin.vacaciones')->parameters(['vacaciones' => 'vacacion']);

    // ---- Seguridad
    Route::resource('usuarios', UsuarioController::class)->names('admin.usuarios')->parameters(['usuarios' => 'usuario']);
    Route::get('bitacora', [BitacoraController::class, 'index'])->name('admin.bitacora.index');

    // ---- Programación
    Route::get('turnos/buscar', [TurnoController::class, 'buscar'])->name('admin.turnos.buscar');
    Route::resource('turnos', TurnoController::class)->names('admin.turnos')->parameters(['turnos' => 'turno']);
    // La ruta fija va antes del resource para que no la capture {zona}.
    Route::get('zonas/direccion', [ZonaController::class, 'buscarDireccion'])->middleware('throttle:40,1')->name('admin.zonas.direccion');
    Route::post('zonas/verificar', [ZonaController::class, 'verificar'])->name('admin.zonas.verificar');
    Route::get('zonas/mapa', [ZonaController::class, 'mapa'])->name('admin.zonas.mapa');
    Route::get('zonas/buscar', [ZonaController::class, 'buscar'])->name('admin.zonas.buscar');
    Route::resource('zonas', ZonaController::class)->names('admin.zonas');
    // La ruta fija va antes del resource para que no la capture {feriado}.
    // ---- Grupos de personal (plantillas) y programaciones
    Route::get('grupos-personal/buscar', [GrupoPersonalController::class, 'buscar'])->name('admin.grupos.buscar');
    Route::get('grupos-personal/{grupo}/plantilla', [GrupoPersonalController::class, 'plantilla'])->name('admin.grupos.plantilla');
    Route::resource('grupos-personal', GrupoPersonalController::class)->names('admin.grupos')->parameters(['grupos-personal' => 'grupo']);

    Route::post('programaciones/validar', [ProgramacionController::class, 'validar'])->name('admin.programaciones.validar');
    Route::get('programaciones/masiva', [ProgramacionController::class, 'masiva'])->name('admin.programaciones.masiva');
    Route::post('programaciones/masiva/validar', [ProgramacionController::class, 'validarMasiva'])->name('admin.programaciones.masiva.validar');
    Route::post('programaciones/masiva', [ProgramacionController::class, 'guardarMasiva'])->name('admin.programaciones.masiva.guardar');
    Route::get('programaciones/reemplazo', [ProgramacionController::class, 'reemplazo'])->name('admin.programaciones.reemplazo');
    Route::post('programaciones/reemplazo/validar', [ProgramacionController::class, 'validarReemplazo'])->name('admin.programaciones.reemplazo.validar');
    Route::post('programaciones/reemplazo', [ProgramacionController::class, 'guardarReemplazo'])->name('admin.programaciones.reemplazo.guardar');
    Route::post('programaciones/feriados/quitar', [ProgramacionController::class, 'quitarEnFeriados'])->name('admin.programaciones.feriados.quitar');
    Route::get('programaciones/dashboard', [DashboardProgramacionController::class, 'index'])->name('admin.programaciones.dashboard');
    Route::get('programaciones/masiva/feriados', [ProgramacionController::class, 'feriadosDelRango'])->name('admin.programaciones.masiva.feriados');
    Route::get('programaciones/grupo/{grupo}/detalle', [ProgramacionController::class, 'detalleDeGrupo'])->name('admin.programaciones.grupo');
    Route::get('programaciones/{programacion}/candidatos', [ProgramacionController::class, 'candidatos'])->name('admin.programaciones.candidatos');
    Route::post('programaciones/{programacion}/validar', [ProgramacionController::class, 'validarCambio'])->name('admin.programaciones.validar.cambio');
    Route::patch('programaciones/{programacion}/finalizar', [ProgramacionController::class, 'finalizar'])->name('admin.programaciones.finalizar');
    Route::get('programaciones/{programacion}/historial', [ProgramacionController::class, 'historial'])->name('admin.programaciones.historial');
    Route::resource('programaciones', ProgramacionController::class)->names('admin.programaciones')->parameters(['programaciones' => 'programacion']);

    // ---- Gestión de cambios (Avance 04): motivos y cambios de programación
    Route::resource('motivos', MotivoController::class)->names('admin.motivos')->parameters(['motivos' => 'motivo']);
    Route::get('cambios/nuevo', [CambioController::class, 'create'])->name('admin.cambios.create');
    Route::post('cambios/validar', [CambioController::class, 'validar'])->name('admin.cambios.validar');
    Route::get('cambios', [CambioController::class, 'index'])->name('admin.cambios.index');
    Route::post('cambios', [CambioController::class, 'store'])->name('admin.cambios.store');
    Route::get('cambios/detalle/{cambio}', [CambioController::class, 'detalle'])->name('admin.cambios.detalle');
    Route::get('cambios/{lote}', [CambioController::class, 'show'])->name('admin.cambios.show');
    Route::delete('cambios/{lote}', [CambioController::class, 'destroy'])->name('admin.cambios.destroy');

    Route::get('feriados/cargar/consultar', [FeriadoController::class, 'consultar'])->middleware('throttle:10,1')->name('admin.feriados.consultar');
    Route::post('feriados/cargar', [FeriadoController::class, 'cargar'])->name('admin.feriados.cargar');
    Route::resource('feriados', FeriadoController::class)->names('admin.feriados');
});
