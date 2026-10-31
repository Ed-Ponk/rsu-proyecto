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
                <div class="t-atajos mb-3" role="group" aria-label="Rangos rápidos de fechas">
                    <span class="small fw-semibold text-muted me-1">Ver:</span>
                    <button type="button" class="t-atajo" data-atajo="hoy">Hoy</button>
                    <button type="button" class="t-atajo" data-atajo="semana">Esta semana</button>
                    <button type="button" class="t-atajo" data-atajo="mes">Este mes</button>
                    <button type="button" class="t-atajo" data-atajo="tres" aria-pressed="true">Próximos 3 meses</button>
                </div>
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
                        <label class="form-label fw-semibold small" for="filtroGrupo">Grupo</label>
                        <select id="filtroGrupo" class="form-select">
                            <option value="">Todos los grupos</option>
                            @foreach ($grupos as $grupo)
                                <option value="{{ $grupo->id }}">{{ $grupo->name }}</option>
                            @endforeach
                            <option value="sin">Sin grupo</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroPersona">Persona</label>
                        <select id="filtroPersona" class="form-select">
                            <option value="">Todo el personal</option>
                            @foreach ($personas as $persona)
                                <option value="{{ $persona->id }}">{{ $persona->nombre_completo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroEstado">Estado</label>
                        <select id="filtroEstado" class="form-select">
                            <option value="">Todos</option>
                            @foreach (\App\Models\Programacion::ESTADOS as $valor => $texto)
                                <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>
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

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="t-vistas" role="tablist" aria-label="Forma de ver las programaciones">
                <button type="button" class="t-vista" data-vista="resumen" role="tab" aria-selected="true"><i class="bi bi-grid-3x2-gap-fill me-1"></i> Resumen por grupo</button>
                <button type="button" class="t-vista" data-vista="detalle" role="tab" aria-selected="false"><i class="bi bi-list-ul me-1"></i> Detalle por día</button>
            </div>
            <span class="small text-muted" id="textoVista"></span>
        </div>

        <div id="panelResumen">
            <div class="t-fichas-grupo" id="resumenGrupos"></div>
        </div>

        <div class="t-tarjeta" id="panelDetalle" hidden>
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
