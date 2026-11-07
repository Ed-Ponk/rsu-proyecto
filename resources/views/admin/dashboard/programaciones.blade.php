@extends('adminlte::page')

@section('title', 'Dashboard de programación')

@section('content')
    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-speedometer2"></i></span>
                Dashboard de programación
            </h1>
            <p class="t-subtitulo">Qué programaciones están completas y cuáles tienen personal faltante. «Ver detalles» permite reemplazar a quien falta.</p>
        </div>
        <a href="{{ route('admin.programaciones.index') }}" class="btn t-boton t-boton-secundario"><i class="bi bi-clock-history me-1"></i> Ir a programaciones</a>
    </div>

    <div class="t-modulo"
         id="moduloDashboardProgramacion"
         data-tads-modulo="dashboardProgramacion"
         data-url-listado="{{ route('admin.programaciones.dashboard') }}"
         data-url-programaciones="{{ url('admin/programaciones') }}">

        <div class="av-kpis" id="kpisDashboard">
            <div data-kpi="total"><x-admin.kpi-card titulo="Total de programaciones" valor="0" icono="bi-calendar2-check-fill" variante="azul" /></div>
            <div data-kpi="completas"><x-admin.kpi-card titulo="Completas" valor="0" icono="bi-check-circle-fill" variante="verde" /></div>
            <div data-kpi="incompletas"><x-admin.kpi-card titulo="Incompletas" valor="0" icono="bi-exclamation-triangle-fill" variante="ambar" /></div>
            <div data-kpi="faltantes"><x-admin.kpi-card titulo="Personal faltante" valor="0" icono="bi-person-x-fill" variante="rojo" /></div>
        </div>

        <div class="t-tarjeta mb-3">
            <div class="t-tarjeta-cuerpo">
                <div class="t-filtros">
                    <div>
                        <label class="form-label fw-semibold small" for="filtroFecha">Fecha</label>
                        <input type="date" id="filtroFecha" class="form-control" value="{{ $fecha }}">
                    </div>
                    <div>
                        <label class="form-label fw-semibold small" for="filtroTurno">Turno</label>
                        <select id="filtroTurno" class="form-select">
                            <option value="">Todos los turnos</option>
                            @foreach ($turnos as $turno)
                                <option value="{{ $turno->id }}">{{ $turno->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn t-boton" id="botonActualizar"><i class="bi bi-funnel-fill me-1"></i> Filtrar</button>
                        <button type="button" class="btn btn-outline-secondary rounded-3" id="botonHoy"><i class="bi bi-calendar-day me-1"></i> Hoy</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="av-zonas" id="zonasDashboard"></div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-xl'])
@stop
