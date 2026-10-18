@extends('adminlte::page')

@section('title', 'Contratos')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-file-earmark-text',
        'titulo' => 'Lista de contratos',
        'subtitulo' => 'Los contratos vigentes determinan qué personal puede asignarse en las programaciones.',
        'boton' => 'Nuevo contrato',
    ])

    <div class="t-modulo"
         id="moduloContratos"
         data-tads-modulo="contratos"
         data-url-listado="{{ route('admin.contratos.index') }}"
         data-url-recurso="{{ url('admin/contratos') }}"
         data-url-crear="{{ route('admin.contratos.create') }}">
        <div class="t-tarjeta mb-3">
            <div class="t-tarjeta-cuerpo">
                <div class="t-filtros">
                    <div>
                        <label class="form-label fw-semibold small" for="filtroCriterio">Filtrar por</label>
                        <select id="filtroCriterio" class="form-select">
                            @foreach ($criterios as $valor => $texto)
                                <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroDesde">Desde</label>
                        <input type="date" id="filtroDesde" class="form-control">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroHasta">Hasta</label>
                        <input type="date" id="filtroHasta" class="form-control">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroTipo">Tipo de contrato</label>
                        <select id="filtroTipo" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($tipos as $valor => $texto)
                                <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn t-boton" id="botonFiltrar"><i class="bi bi-funnel-fill me-1"></i> Filtrar</button>
                        <button type="button" class="btn btn-outline-secondary rounded-3" id="botonLimpiar"><i class="bi bi-eraser me-1"></i> Limpiar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="t-tarjeta">
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
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@stop
