@extends('adminlte::page')

@section('title', 'Feriados')

@section('content')
    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-flag-fill"></i></span>
                Listado de feriados
            </h1>
            <p class="t-subtitulo">Los feriados activos afectan la programación de rutas.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn t-boton t-boton-secundario" id="botonCargarFeriados"><i class="bi bi-calendar-check me-1"></i> Cargar Feriados Perú</button>
            <button type="button" class="btn t-boton" id="botonNuevo"><i class="bi bi-plus-lg me-1"></i> Nuevo feriado</button>
        </div>
    </div>

    <div class="t-modulo"
         id="moduloFeriados"
         data-tads-modulo="feriados"
         data-url-listado="{{ route('admin.feriados.index') }}"
         data-url-recurso="{{ url('admin/feriados') }}"
         data-url-crear="{{ route('admin.feriados.create') }}"
         data-url-cargar="{{ route('admin.feriados.cargar') }}"
         data-url-consultar="{{ route('admin.feriados.consultar') }}"
         data-anio="{{ $resumen['anio'] }}">

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3" data-kpi="total"><x-admin.kpi-card titulo="Total feriados" :valor="$resumen['total']" icono="bi-flag-fill" variante="azul" /></div>
            <div class="col-6 col-lg-3" data-kpi="activos"><x-admin.kpi-card titulo="Feriados activos" :valor="$resumen['activos']" icono="bi-check-circle-fill" variante="verde" /></div>
            <div class="col-6 col-lg-3" data-kpi="proximos"><x-admin.kpi-card titulo="Próximos feriados" :valor="$resumen['proximos']" icono="bi-hourglass-split" variante="ambar" /></div>
            <div class="col-6 col-lg-3" data-kpi="anio"><x-admin.kpi-card titulo="Año actual" :valor="$resumen['anio']" icono="bi-calendar3" variante="violeta" /></div>
        </div>

        <div class="t-tarjeta mb-3">
            <div class="t-tarjeta-cuerpo">
                <div class="t-filtros">
                    <div>
                        <label class="form-label fw-semibold small" for="filtroDesde">Fecha de inicio</label>
                        <input type="date" id="filtroDesde" class="form-control">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroHasta">Fecha de fin</label>
                        <input type="date" id="filtroHasta" class="form-control">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroEstado">Estado</label>
                        <select id="filtroEstado" class="form-select">
                            <option value="">Todos</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
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
                                <th>Fecha</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Día</th>
                                <th>Creación</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-md'])
@stop
