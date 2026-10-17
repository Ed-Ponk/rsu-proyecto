@extends('adminlte::page')

@section('title', 'Modelos')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-wrench-adjustable',
        'titulo' => 'Lista de modelos',
        'subtitulo' => 'Modelos de vehículo; cada uno pertenece a una marca.',
        'boton' => 'Nuevo modelo',
    ])

    <div class="t-tarjeta"
         id="moduloModelos"
         data-tads-modulo="modelos"
         data-url-listado="{{ route('admin.modelos.index') }}"
         data-url-recurso="{{ url('admin/modelos') }}"
         data-url-crear="{{ route('admin.modelos.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Marca</th>
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
