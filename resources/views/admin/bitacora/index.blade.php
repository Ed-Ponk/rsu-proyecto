@extends('adminlte::page')

@section('title', 'Bitácora')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-clipboard-data',
        'titulo' => 'Bitácora de cambios',
        'subtitulo' => 'Quién creó, modificó o eliminó cada registro. Se muestran los últimos 1 000 movimientos.',
    ])

    <div class="t-modulo"
         id="moduloBitacora"
         data-tads-modulo="bitacora"
         data-url-listado="{{ route('admin.bitacora.index') }}">
        <div class="t-tarjeta mb-3">
            <div class="t-tarjeta-cuerpo">
                <div class="t-filtros">
                    <div>
                        <label class="form-label fw-semibold small" for="filtroModelo">Registro</label>
                        <select id="filtroModelo" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($modelos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroAccion">Acción</label>
                        <select id="filtroAccion" class="form-select">
                            <option value="">Todas</option>
                            <option value="creado">Creado</option>
                            <option value="modificado">Modificado</option>
                            <option value="eliminado">Eliminado</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn t-boton" id="botonFiltrar"><i class="bi bi-funnel-fill me-1"></i> Filtrar</button>
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
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Registro</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
