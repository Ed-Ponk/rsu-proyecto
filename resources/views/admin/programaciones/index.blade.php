@extends('adminlte::page')

@section('title', 'Programación')

{{-- Botones Excel / PDF / Imprimir / Columnas de la tabla (solo en esta pantalla). --}}
@section('plugins.DatatablesButtons', true)

@section('content')
    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-clock-history"></i></span>
                Lista de programaciones
            </h1>
            <p class="t-subtitulo">Cada fila es un día de trabajo programado. Los grupos de personal permiten generarlas rápido.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.inicio') }}" class="btn t-boton t-boton-secundario"><i class="bi bi-speedometer2 me-1"></i> Ir al Dashboard</a>
            <button type="button" class="btn t-boton" id="botonNuevo"><i class="bi bi-plus-lg me-1"></i> Nueva programación</button>
            <button type="button" class="btn t-boton t-boton-secundario" id="botonReemplazo"><i class="bi bi-arrow-left-right me-1"></i> Reemplazar</button>
            <button type="button" class="btn t-boton t-boton-peligro" id="botonMasiva"><i class="bi bi-calendar2-range-fill me-1"></i> Programación masiva</button>
        </div>
    </div>

    <div class="t-modulo"
         id="moduloProgramaciones"
         data-tads-modulo="programaciones"
         data-url-listado="{{ route('admin.programaciones.index') }}"
         data-url-recurso="{{ url('admin/programaciones') }}"
         data-url-crear="{{ route('admin.programaciones.create') }}"
         data-url-masiva="{{ route('admin.programaciones.masiva') }}"
         data-url-reemplazo="{{ route('admin.programaciones.reemplazo') }}"
         data-url-quitar-feriados="{{ route('admin.programaciones.feriados.quitar') }}">

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
                        <label class="form-label fw-semibold small" for="filtroZona">Zona</label>
                        <select id="filtroZona" class="form-select">
                            <option value="">Todas las zonas</option>
                            @foreach (\App\Models\Zona::orderBy('name')->get(['id', 'name']) as $zona)
                                <option value="{{ $zona->id }}">{{ $zona->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroTurno">Turno</label>
                        <select id="filtroTurno" class="form-select">
                            <option value="">Todos los turnos</option>
                            @foreach (\App\Models\Turno::orderBy('time_start')->get(['id', 'name']) as $turno)
                                <option value="{{ $turno->id }}">{{ $turno->name }}</option>
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
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div class="t-exportar" id="botonesExportar"></div>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3" id="botonQuitarFeriados" hidden>
                        <i class="bi bi-flag-fill me-1"></i> Quitar las de feriados
                    </button>
                </div>
                <div class="t-nota t-nota-ambar mb-3" id="avisoTruncado" hidden>
                    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Hay más resultados:</strong>
                    se muestran los primeros 2 000. Acote el rango de fechas, la zona o el turno.
                </div>
                <div class="table-responsive">
                    <table class="table t-tabla" id="tabla">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Zona</th>
                                <th>Turno</th>
                                <th>Vehículo</th>
                                <th>Conductor</th>
                                <th>Ayudantes</th>
                                <th>Observaciones</th>
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
