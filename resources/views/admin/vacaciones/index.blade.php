@extends('adminlte::page')

@section('title', 'Vacaciones')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-airplane-fill',
        'titulo' => 'Lista de vacaciones',
        'subtitulo' => 'Al aprobar una solicitud se descuentan los días del año actual. Las vacaciones aprobadas sacan al personal de las programaciones.',
        'boton' => 'Nueva solicitud',
    ])

    <div class="t-tarjeta"
         id="moduloVacaciones"
         data-tads-modulo="vacaciones"
         data-permiso="vacations"
         data-permisos="{{ auth()->user()->permisosParaVista() }}"
         data-url-listado="{{ route('admin.vacaciones.index') }}"
         data-url-recurso="{{ url('admin/vacaciones') }}"
         data-url-personal="{{ url('admin/personal') }}"
         data-url-crear="{{ route('admin.vacaciones.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Empleado</th>
                            <th>Fecha solicitud</th>
                            <th>Fecha de inicio</th>
                            <th>Fecha de término</th>
                            <th>Días S.</th>
                            <th>Estado</th>
                            <th>Días R.</th>
                            <th>Notas</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="form-text mt-2"><strong>Días S.</strong>: días solicitados · <strong>Días R.</strong>: días que le quedan a la persona en el año de las vacaciones.</div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@stop
