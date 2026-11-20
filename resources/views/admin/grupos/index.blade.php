@extends('adminlte::page')

@section('title', 'Grupos de personal')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-people-fill',
        'titulo' => 'Lista de grupos de personal',
        'subtitulo' => 'Plantillas con turno, zona, vehículo, días, conductor y ayudantes: para programar solo se indican las fechas.',
        'boton' => 'Nuevo grupo',
    ])

    <div class="t-tarjeta"
         id="moduloGrupos"
         data-tads-modulo="grupos"
         data-permiso="employeegroups"
         data-permisos="{{ auth()->user()->permisosParaVista() }}"
         data-url-listado="{{ route('admin.grupos.index') }}"
         data-url-recurso="{{ url('admin/grupos-personal') }}"
         data-url-crear="{{ route('admin.grupos.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Turno</th>
                            <th>Zona</th>
                            <th>Vehículo</th>
                            <th>Conductor</th>
                            <th>Ayudantes</th>
                            <th>Días</th>
                            <th>Estado</th>
                            <th>Creación</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@stop
