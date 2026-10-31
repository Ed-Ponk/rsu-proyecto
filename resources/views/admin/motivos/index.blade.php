@extends('adminlte::page')

@section('title', 'Motivos de cambio')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-chat-left-text-fill',
        'titulo' => 'Lista de motivos',
        'subtitulo' => 'Motivos predefinidos que se eligen al cambiar el turno, el vehículo o el personal de una programación.',
        'boton' => 'Nuevo motivo',
    ])

    <div class="t-tarjeta"
         id="moduloMotivos"
         data-tads-modulo="motivos"
         data-url-listado="{{ route('admin.motivos.index') }}"
         data-url-recurso="{{ url('admin/motivos') }}"
         data-url-crear="{{ route('admin.motivos.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Fecha creación</th>
                            <th>Fecha actualización</th>
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
