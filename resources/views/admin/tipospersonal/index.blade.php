@extends('adminlte::page')

@section('title', 'Tipos de personal')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-person-badge',
        'titulo' => 'Lista de tipos de personal',
        'subtitulo' => 'Funciones del personal. «Conductor» y «Ayudante» son obligatorias y no se pueden eliminar.',
        'boton' => 'Nuevo tipo',
    ])

    <div class="t-tarjeta"
         id="moduloTiposPersonal"
         data-tads-modulo="tipospersonal"
         data-url-listado="{{ route('admin.tipospersonal.index') }}"
         data-url-recurso="{{ url('admin/tipos-personal') }}"
         data-url-crear="{{ route('admin.tipospersonal.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Personal</th>
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
