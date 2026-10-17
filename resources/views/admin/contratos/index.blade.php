@extends('adminlte::page')

@section('title', 'Contratos')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-file-earmark-text',
        'titulo' => 'Lista de contratos',
        'subtitulo' => 'Los contratos vigentes determinan qué personal puede asignarse en las programaciones.',
        'boton' => 'Nuevo contrato',
    ])

    <div class="t-tarjeta"
         id="moduloContratos"
         data-tads-modulo="contratos"
         data-url-listado="{{ route('admin.contratos.index') }}"
         data-url-recurso="{{ url('admin/contratos') }}"
         data-url-crear="{{ route('admin.contratos.create') }}">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Empleado</th>
                            <th>Tipo de contrato</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Salario</th>
                            <th>Posición</th>
                            <th>Estado</th>
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
