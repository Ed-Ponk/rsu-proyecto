@extends('adminlte::page')

@section('title', 'Cambios')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-arrow-left-right',
        'titulo' => 'Cambios de programaciones',
        'subtitulo' => 'Cambios de turno, vehículo, conductor y ocupantes hechos a las programaciones.',
        'boton' => 'Nuevo cambio masivo',
    ])

    <div class="t-modulo"
         id="moduloCambios"
         data-tads-modulo="cambios"
         data-url-listado="{{ route('admin.cambios.index') }}"
         data-url-recurso="{{ url('admin/cambios') }}"
         data-url-detalle="{{ url('admin/cambios/detalle') }}"
         data-url-crear="{{ route('admin.cambios.create') }}">

        <div class="t-tarjeta mb-3">
            <div class="t-tarjeta-cuerpo">
                <div class="t-filtros">
                    <div>
                        <label class="form-label fw-semibold small" for="filtroDesde">Fecha de inicio</label>
                        <input type="date" id="filtroDesde" class="form-control" value="{{ $desde }}">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroHasta">Fecha de fin</label>
                        <input type="date" id="filtroHasta" class="form-control" value="{{ $hasta }}">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroTipo">Tipo de cambio</label>
                        <select id="filtroTipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            @foreach (\App\Models\Cambio::TIPOS as $valor => $texto)
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
                                <th>Tipo de cambio</th>
                                <th>Fecha del cambio</th>
                                <th>Antes</th>
                                <th>Después</th>
                                <th>Motivo</th>
                                <th>Realizado por</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-xl'])
@stop
