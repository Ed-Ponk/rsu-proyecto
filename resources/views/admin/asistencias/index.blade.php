@extends('adminlte::page')

@section('title', 'Asistencias')

@section('content')
    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-calendar-check"></i></span>
                Listado de asistencias
            </h1>
            <p class="t-subtitulo">Por defecto se muestran las de hoy. Las asistencias deciden si un vehículo puede iniciar su recorrido.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.asistencias.marcacion') }}" class="btn btn-outline-secondary rounded-3"><i class="bi bi-fingerprint me-1"></i> Marcación DNI</a>
            <button type="button" class="btn t-boton" id="botonNuevo"><i class="bi bi-plus-lg me-1"></i> Agregar asistencia</button>
        </div>
    </div>

    <div class="t-modulo"
         id="moduloAsistencias"
         data-tads-modulo="asistencias"
         data-url-listado="{{ route('admin.asistencias.index') }}"
         data-url-recurso="{{ url('admin/asistencias') }}"
         data-url-crear="{{ route('admin.asistencias.create') }}"
         data-url-vista-previa="{{ route('admin.asistencias.vistaprevia') }}"
         data-hoy="{{ $hoy }}">
        <div class="t-tarjeta mb-3">
            <div class="t-tarjeta-cuerpo">
                <div class="t-filtros">
                    <div>
                        <label class="form-label fw-semibold small" for="filtroDesde">Fecha de inicio</label>
                        <input type="date" id="filtroDesde" class="form-control" value="{{ $hoy }}" max="{{ $hoy }}">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroHasta">Fecha de fin</label>
                        <input type="date" id="filtroHasta" class="form-control" value="{{ $hoy }}" max="{{ $hoy }}">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroTexto">Buscar empleado</label>
                        <input type="search" id="filtroTexto" class="form-control" placeholder="DNI, nombre o apellido...">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn t-boton" id="botonFiltrar"><i class="bi bi-funnel-fill me-1"></i> Filtrar</button>
                        <button type="button" class="btn btn-outline-secondary rounded-3" id="botonHoy"><i class="bi bi-eraser me-1"></i> Hoy</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="t-tarjeta">
            <div class="t-tarjeta-cuerpo">
                <div class="table-responsive">
                    <table class="table t-tabla" id="tabla">
                        <thead>
                            <tr>
                                <th>DNI</th>
                                <th>Empleado</th>
                                <th>Cargo</th>
                                <th>Fecha y hora</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Turno</th>
                                <th>Origen</th>
                                <th>Notas</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@stop
