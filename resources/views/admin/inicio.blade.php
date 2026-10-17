@extends('adminlte::page')

@section('title', 'Centro de Mando Operativo - RSU')

@section('content')
    <div class="t-inicio">
        {{-- Cabecera Institucional del Módulo --}}
        <x-admin.cabecera
            titulo="Centro de Mando Operativo RSU"
            subtitulo="Monitoreo en tiempo real de flota vehicular, cuadrillas en turno y compromisos laborales"
            icono="bi-speedometer2"
        >
            <x-slot:acciones>
                <div class="d-flex align-items-center gap-2">
                    <span class="d-none d-md-inline-block text-muted small">
                        <i class="bi bi-clock me-1"></i> Actualizado: {{ now()->format('H:i') }}
                    </span>
                    <x-admin.boton href="{{ route('admin.asistencias.index') }}" variante="primary" icono="bi-clock-fill" texto="Marcar Asistencia" tamano="sm" />
                </div>
            </x-slot:acciones>
        </x-admin.cabecera>

        {{-- Banner Municipal con Identidad Institucional y Situación Operativa --}}
        <section class="t-dashboard-banner">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="t-banner-distrito">
                            <i class="bi bi-shield-shaded me-1"></i> {{ $municipalidad }}
                        </span>
                        <span class="t-pulso-operativo">
                            <span class="t-pulso-punto"></span>
                            <span>Sistema Operativo en Vivo</span>
                        </span>
                        @php
                            $horaActual = (int) now()->format('H');
                            $turnoTexto = ($horaActual >= 6 && $horaActual < 14)
                                ? 'Turno Mañana (06:00 - 14:00)'
                                : (($horaActual >= 14 && $horaActual < 22)
                                    ? 'Turno Tarde (14:00 - 22:00)'
                                    : 'Turno Noche (22:00 - 06:00)');
                        @endphp
                        <span class="badge bg-dark bg-opacity-50 text-light border border-light border-opacity-25 py-1 px-2" style="font-size: 0.76rem;">
                            <i class="bi bi-sun-fill text-warning me-1"></i> {{ $turnoTexto }}
                        </span>
                    </div>
                    <h1 class="t-banner-titulo">
                        Sistema de Gestión de Residuos Sólidos Urbanos
                    </h1>
                    <p class="t-banner-subtitulo">
                        Supervisión integral de rutas de recolección, disponibilidad mecánica de unidades compactadoras y control de asistencia del personal operativo en el distrito de José Leonardo Ortiz.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="d-inline-flex flex-column align-items-lg-end bg-black bg-opacity-25 p-3 rounded-3 border border-white border-opacity-10 text-start text-lg-end">
                        <span class="text-uppercase small text-success fw-bold tracking-wider" style="font-size: 0.72rem; letter-spacing: 0.08em;">
                            <i class="bi bi-calendar3 me-1"></i> Fecha de Operación
                        </span>
                        <span class="fw-bold fs-5 text-white my-1">
                            {{ ucfirst(\Carbon\Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY')) }}
                        </span>
                        <span class="text-white-50 small">
                            Subgerencia de Gestión Ambiental y Residuos Sólidos
                        </span>
                    </div>
                </div>
            </div>
        </section>

        {{-- 4 Indicadores Clave de Rendimiento (KPIs Primarios) --}}
        <section class="t-kpi-grid">
            {{-- KPI 1: Flota Vehicular --}}
            @php
                $pctFlota = ($kpis['vehiculos_total'] > 0)
                    ? round(($kpis['vehiculos_operativos'] / $kpis['vehiculos_total']) * 100)
                    : 0;
            @endphp
            <x-admin.kpi-card
                titulo="Flota Vehicular"
                valor="{{ $kpis['vehiculos_operativos'] }} / {{ $kpis['vehiculos_total'] }}"
                icono="bi-truck-front-fill"
                variante="esmeralda"
                subtexto="{{ $kpis['vehiculos_operativos'] }} operativos · {{ $kpis['vehiculos_taller'] }} en taller ({{ number_format($kpis['capacidad_total_toneladas'], 1) }} Tn total)"
                :progreso="$pctFlota"
                enlace="{{ route('admin.vehiculos.index') }}"
            />

            {{-- KPI 2: Personal en Turno --}}
            @php
                $pctPersonal = ($kpis['personal_total'] > 0)
                    ? round(($kpis['personal_turno_hoy'] / $kpis['personal_total']) * 100)
                    : 0;
            @endphp
            <x-admin.kpi-card
                titulo="Personal en Turno"
                valor="{{ $kpis['personal_turno_hoy'] }} / {{ $kpis['personal_total'] }}"
                icono="bi-people-fill"
                variante="pizarra"
                subtexto="Presentes hoy en jornada vs total de personal registrado"
                :progreso="$pctPersonal"
                enlace="{{ route('admin.personal.index') }}"
            />

            {{-- KPI 3: Contratos Vigentes --}}
            <x-admin.kpi-card
                titulo="Contratos Vigentes"
                valor="{{ $kpis['contratos_vigentes'] }}"
                icono="bi-file-earmark-check-fill"
                variante="esmeralda"
                subtexto="Personal formalizado con contrato activo en el sistema"
                enlace="{{ route('admin.contratos.index') }}"
            />

            {{-- KPI 4: Alerta de Vencimiento de Contratos (30 días) --}}
            <x-admin.kpi-card
                titulo="Contratos por Vencer"
                valor="{{ $kpis['contratos_por_vencer'] }}"
                icono="bi-exclamation-triangle-fill"
                variante="{{ $kpis['contratos_por_vencer'] > 0 ? 'ambar' : 'esmeralda' }}"
                subtexto="{{ $kpis['contratos_por_vencer'] > 0 ? 'Vencen dentro de los próximos 30 días' : 'Sin contratos en riesgo en los próximos 30 días' }}"
                enlace="{{ route('admin.contratos.index') }}"
            />
        </section>

        {{-- Panel Operativo Principal en Dos Columnas --}}
        <div class="row g-4">
            {{-- Columna 1: Flota Vehicular y Contratos por Vencer --}}
            <div class="col-lg-7 col-xl-7">
                {{-- Resumen de Flota Vehicular --}}
                <x-admin.tarjeta titulo="Resumen de Flota Vehicular" icono="bi-truck" clase="mb-4">
                    <x-slot:herramientas>
                        <x-admin.badge variante="info" icono="bi-speedometer2">
                            Capacidad: {{ number_format($kpis['capacidad_total_toneladas'], 1) }} Tn
                        </x-admin.badge>
                        <a href="{{ route('admin.vehiculos.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.78rem;">
                            Ver inventario <i class="bi bi-arrow-right"></i>
                        </a>
                    </x-slot:herramientas>

                    @if($flota_resumen->isEmpty())
                        <x-admin.empty-state
                            icono="bi-truck"
                            titulo="Sin unidades en inventario"
                            descripcion="No se encontraron vehículos registrados en la flota de recolección municipal."
                            accion="Gestionar Vehículos"
                            accion-url="{{ route('admin.vehiculos.index') }}"
                            :compacto="true"
                        />
                    @else
                        <div class="t-flota-listado">
                            @foreach($flota_resumen as $tipo)
                                @php
                                    $tTotal = (int) ($tipo->total ?? 0);
                                    $tOperativos = (int) ($tipo->operativos ?? 0);
                                    $tTaller = (int) ($tipo->taller ?? 0);
                                    $pctOperativo = $tTotal > 0 ? round(($tOperativos / $tTotal) * 100) : 0;
                                    $pctTaller = $tTotal > 0 ? round(($tTaller / $tTotal) * 100) : 0;
                                @endphp
                                <div class="t-flota-item">
                                    <div class="t-flota-encabezado">
                                        <span class="t-flota-nombre">
                                            <i class="bi bi-truck-front text-muted"></i> {{ $tipo->name ?? 'Tipo de Vehículo' }}
                                        </span>
                                        <div class="d-flex align-items-center gap-2 t-flota-conteos">
                                            <x-admin.badge variante="activo" tamano="sm">
                                                {{ $tOperativos }} operativos
                                            </x-admin.badge>
                                            @if($tTaller > 0)
                                                <x-admin.badge variante="ambar" tamano="sm">
                                                    {{ $tTaller }} en taller
                                                </x-admin.badge>
                                            @endif
                                            <span class="text-muted small">Total: <strong>{{ $tTotal }}</strong></span>
                                        </div>
                                    </div>
                                    <div class="t-flota-barra-dual">
                                        <div class="t-barra-operativa" style="width: {{ $pctOperativo }}%;" title="{{ $pctOperativo }}% Operativos"></div>
                                        <div class="t-barra-taller" style="width: {{ $pctTaller }}%;" title="{{ $pctTaller }}% en Taller"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-admin.tarjeta>

                {{-- Contratos Próximos a Vencer --}}
                <x-admin.tarjeta titulo="Contratos Próximos a Vencer (30 Días)" icono="bi-clock-history" sin-padding>
                    <x-slot:herramientas>
                        @if($contratos_criticos->isNotEmpty())
                            <x-admin.badge variante="por-vencer" icono="bi-exclamation-circle-fill">
                                {{ $contratos_criticos->count() }} alertas
                            </x-admin.badge>
                        @else
                            <x-admin.badge variante="activo" icono="bi-check-circle-fill">
                                Al día
                            </x-admin.badge>
                        @endif
                        <a href="{{ route('admin.contratos.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.78rem;">
                            Gestionar <i class="bi bi-arrow-right"></i>
                        </a>
                    </x-slot:herramientas>

                    @if($contratos_criticos->isEmpty())
                        <div class="p-3">
                            <x-admin.empty-state
                                icono="bi-file-earmark-check"
                                titulo="Todos los contratos están al día"
                                descripcion="No existen contratos temporales con vencimiento en los próximos 30 días. El personal activo cuenta con estabilidad contractual vigente."
                                :compacto="true"
                            />
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table t-tabla align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Trabajador</th>
                                        <th>Cargo / Función</th>
                                        <th>Fecha Fin</th>
                                        <th class="text-center">Días Restantes</th>
                                        <th class="text-end">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($contratos_criticos as $contrato)
                                        @php
                                            $dias = $contrato->dias_restantes ?? (int) max(0, today()->diffInDays($contrato->end_date, false));
                                            $badgeVariante = ($dias <= 7) ? 'rojo' : 'ambar';
                                            $badgeTexto = ($dias === 0) ? 'Vence hoy' : (($dias === 1) ? '1 día restante' : "{$dias} días restantes");
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="t-avatar" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                        {{ strtoupper(substr($contrato->personal?->name ?? 'P', 0, 1) . substr($contrato->personal?->last_name ?? 'P', 0, 1)) }}
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                                            {{ $contrato->personal?->name }} {{ $contrato->personal?->last_name }}
                                                        </div>
                                                        <small class="text-muted font-monospace" style="font-size: 0.74rem;">
                                                            DNI: {{ $contrato->personal?->dni }}
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-dark small fw-medium">
                                                    {{ $contrato->personal?->tipo?->name ?? 'Personal' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="t-codigo">
                                                    {{ $contrato->end_date ? \Carbon\Carbon::parse($contrato->end_date)->format('d/m/Y') : '-' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <x-admin.badge :variante="$badgeVariante" tamano="sm" :dot="$dias <= 7">
                                                    {{ $badgeTexto }}
                                                </x-admin.badge>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('admin.contratos.index') }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                                    <i class="bi bi-pencil-square me-1"></i> Renovar
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-admin.tarjeta>
            </div>

            {{-- Columna 2: Monitoreo de Asistencias y Accesos Rápidos --}}
            <div class="col-lg-5 col-xl-5">
                {{-- Monitoreo de Asistencias en Vivo --}}
                <x-admin.tarjeta titulo="Monitoreo de Asistencias en Vivo" icono="bi-clock-fill" clase="mb-4">
                    <x-slot:herramientas>
                        <x-admin.badge variante="activo" dot texto="Hoy" />
                        <a href="{{ route('admin.asistencias.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.78rem;">
                            Historial <i class="bi bi-arrow-right"></i>
                        </a>
                    </x-slot:herramientas>

                    @if($asistencias_recientes->isEmpty())
                        <x-admin.empty-state
                            icono="bi-clock-history"
                            titulo="Sin marcaciones el día de hoy"
                            descripcion="Aún no se registran asistencias para la jornada actual. Las marcaciones del quiosco o administrativas aparecerán aquí en vivo."
                            accion="Registrar Marcación"
                            accion-url="{{ route('admin.asistencias.index') }}"
                            :compacto="true"
                        />
                    @else
                        <div class="t-feed-asistencias">
                            @foreach($asistencias_recientes as $asistencia)
                                @php
                                    $esEntrada = strtolower($asistencia->type) === 'entrada';
                                    $horaFormateada = $asistencia->time ? substr($asistencia->time, 0, 5) : '--:--';
                                @endphp
                                <div class="t-asistencia-item">
                                    <div class="t-asistencia-persona">
                                        <div class="t-asistencia-avatar">
                                            {{ strtoupper(substr($asistencia->personal?->name ?? 'A', 0, 1) . substr($asistencia->personal?->last_name ?? 'P', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="t-asistencia-nombre">
                                                {{ $asistencia->personal?->name }} {{ $asistencia->personal?->last_name }}
                                            </div>
                                            <div class="t-asistencia-cargo">
                                                {{ $asistencia->personal?->tipo?->name ?? 'Personal' }}
                                                @if($asistencia->turno)
                                                    · <span class="text-muted">{{ $asistencia->turno->name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="t-asistencia-tiempo">
                                        <span class="t-asistencia-hora">{{ $horaFormateada }}</span>
                                        <x-admin.badge :variante="$esEntrada ? 'activo' : 'ambar'" tamano="sm">
                                            <i class="bi {{ $esEntrada ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right' }} me-1"></i>
                                            {{ ucfirst($asistencia->type) }}
                                        </x-admin.badge>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-admin.tarjeta>

                {{-- Accesos Rápidos Operativos --}}
                <x-admin.tarjeta titulo="Accesos Rápidos Operativos" icono="bi-grid-fill">
                    <div class="t-accesos-grid">
                        <a href="{{ route('admin.asistencias.index') }}" class="t-acceso-card">
                            <div class="t-acceso-icono t-icono-verde">
                                <i class="bi bi-clock-fill"></i>
                            </div>
                            <div class="t-acceso-info">
                                <span class="t-acceso-titulo">Asistencias</span>
                                <span class="t-acceso-desc">Marcaciones y turnos</span>
                            </div>
                            <i class="bi bi-chevron-right t-acceso-flecha"></i>
                        </a>

                        <a href="{{ route('admin.vehiculos.index') }}" class="t-acceso-card">
                            <div class="t-acceso-icono t-icono-azul">
                                <i class="bi bi-truck"></i>
                            </div>
                            <div class="t-acceso-info">
                                <span class="t-acceso-titulo">Vehículos</span>
                                <span class="t-acceso-desc">Flota e inspección</span>
                            </div>
                            <i class="bi bi-chevron-right t-acceso-flecha"></i>
                        </a>

                        <a href="{{ route('admin.contratos.index') }}" class="t-acceso-card">
                            <div class="t-acceso-icono t-icono-ambar">
                                <i class="bi bi-file-earmark-text-fill"></i>
                            </div>
                            <div class="t-acceso-info">
                                <span class="t-acceso-titulo">Contratos</span>
                                <span class="t-acceso-desc">Vigencias y salarios</span>
                            </div>
                            <i class="bi bi-chevron-right t-acceso-flecha"></i>
                        </a>

                        <a href="{{ route('admin.personal.index') }}" class="t-acceso-card">
                            <div class="t-acceso-icono t-icono-pizarra">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="t-acceso-info">
                                <span class="t-acceso-titulo">Personal</span>
                                <span class="t-acceso-desc">Padrón y licencias</span>
                            </div>
                            <i class="bi bi-chevron-right t-acceso-flecha"></i>
                        </a>
                    </div>

                    {{-- Módulos Complementarios e Institucionales --}}
                    @if(!empty($funciones))
                        <div class="mt-3 pt-3 border-top">
                            <span class="text-uppercase text-muted fw-semibold d-block mb-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                Catálogos y Planificación
                            </span>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($funciones as $f)
                                    @if($f['ruta'])
                                        <a href="{{ $f['ruta'] }}" class="btn btn-sm btn-light border text-dark" style="font-size: 0.78rem;">
                                            <i class="bi {{ $f['icono'] }} me-1" style="color: {{ $f['color'] }}"></i> {{ $f['texto'] }}
                                        </a>
                                    @else
                                        <span class="btn btn-sm btn-light border text-muted opacity-75 disabled" style="font-size: 0.78rem;" title="Módulo en desarrollo">
                                            <i class="bi {{ $f['icono'] }} me-1"></i> {{ $f['texto'] }}
                                            <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;">Pronto</span>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </x-admin.tarjeta>
            </div>
        </div>
    </div>
@stop
