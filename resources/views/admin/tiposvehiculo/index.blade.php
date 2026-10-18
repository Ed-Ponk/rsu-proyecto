@extends('adminlte::page')

@section('title', 'Tipos de vehículo')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-truck-flatbed',
        'titulo' => 'Lista de tipos de vehículo',
        'subtitulo' => 'Clasificación de la flota: camión recolector, compactador, volquete...',
        'boton' => 'Nuevo tipo',
    ])

    <div class="t-tarjeta"
         id="moduloTiposVehiculo"
         data-tads-modulo="tiposvehiculo"
         data-url-listado="{{ route('admin.tiposvehiculo.index') }}"
         data-url-recurso="{{ url('admin/tipos-vehiculo') }}"
         data-url-crear="{{ route('admin.tiposvehiculo.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Vehículos</th>
                            <th>Creación</th>
                            <th>Actualización</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-md'])
@stop
