@extends('layouts.admin')

@section('title', 'Marcas')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-tags',
        'titulo' => 'Lista de marcas',
        'subtitulo' => 'Marcas de los vehículos que se usan en la recolección.',
        'boton' => 'Nueva marca',
    ])

    <div class="t-tarjeta"
         id="moduloMarcas"
         data-tads-modulo="marcas"
         data-url-listado="{{ route('admin.marcas.datos') }}"
         data-url-recurso="{{ url('admin/marcas') }}"
         data-url-crear="{{ route('admin.marcas.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Modelos</th>
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

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@stop
