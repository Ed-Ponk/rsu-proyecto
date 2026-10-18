@extends('adminlte::page')

@section('title', 'Turnos')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-clock-fill',
        'titulo' => 'Lista de turnos',
        'subtitulo' => 'Turnos de trabajo que se usan en la asistencia y en las programaciones.',
        'boton' => 'Nuevo turno',
    ])

    <div class="t-tarjeta"
         id="moduloTurnos"
         data-tads-modulo="turnos"
         data-url-listado="{{ route('admin.turnos.index') }}"
         data-url-recurso="{{ url('admin/turnos') }}"
         data-url-crear="{{ route('admin.turnos.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Hora de entrada</th>
                            <th>Hora de salida</th>
                            <th>Asistencias</th>
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
