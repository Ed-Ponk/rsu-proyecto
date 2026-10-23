@extends('adminlte::page')

@section('title', 'Colores')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-palette',
        'titulo' => 'Lista de colores',
        'subtitulo' => 'Colores que se asignan a los vehículos de la flota.',
        'boton' => 'Nuevo color',
    ])

    <div class="t-tarjeta"
         id="moduloColores"
         data-tads-modulo="colores"
         data-url-listado="{{ route('admin.colores.datos') }}"
         data-url-recurso="{{ url('admin/colores') }}"
         data-url-crear="{{ route('admin.colores.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Color</th>
                            <th>Nombre</th>
                            <th>Código</th>
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
