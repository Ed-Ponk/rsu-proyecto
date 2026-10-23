@extends('layouts.admin')

@section('title', 'Personal')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-person-lines-fill',
        'titulo' => 'Lista de personal',
        'subtitulo' => 'Conductores y ayudantes. Solo el personal activo y con contrato vigente puede programarse.',
        'boton' => 'Nuevo personal',
    ])

    <div class="t-tarjeta"
         id="moduloPersonal"
         data-tads-modulo="personal"
         data-url-listado="{{ route('admin.personal.datos') }}"
         data-url-recurso="{{ url('admin/personal') }}"
         data-url-crear="{{ route('admin.personal.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>DNI</th>
                            <th>Nombres</th>
                            <th>Apellidos</th>
                            <th>Email</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Contrato</th>
                            <th>Creación</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-xl'])
@stop
