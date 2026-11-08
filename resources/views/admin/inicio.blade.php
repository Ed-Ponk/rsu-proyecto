@extends('adminlte::page')

@section('title', 'Inicio')

@section('content')
    <div class="t-inicio">
        {{-- Banner de bienvenida --}}
        <section class="t-bienvenida">
            <span class="t-bienvenida-icono"><i class="bi bi-recycle"></i></span>
            <h1><i class="bi bi-star-fill"></i> ¡Bienvenido al Sistema RSU!</h1>
            <p>Sistema de Gestión de Residuos Sólidos Urbanos - {{ $municipalidad }}</p>
            <small><i class="bi bi-info-circle-fill me-1"></i> Utilice el menú lateral para navegar entre los diferentes módulos del sistema</small>

            {{-- Reloj con la hora del servidor y acceso directo al quiosco de marcación --}}
            <div class="t-bienvenida-acciones" data-tads-modulo="inicio"
                 data-url-estado="{{ route('admin.asistencias.marcacion.estado') }}">
                <div class="t-reloj-chip" role="timer" aria-live="off">
                    <span class="t-reloj-chip-icono"><i class="bi bi-clock-history"></i></span>
                    <span class="t-reloj-chip-datos">
                        <strong data-reloj-hora>--:--:--</strong>
                        <small><span data-reloj-fecha>&nbsp;</span> · <span data-reloj-turno>Consultando turno...</span></small>
                    </span>
                </div>
                @can('attendances.create')
                    <a href="{{ route('admin.asistencias.marcacion') }}" class="btn t-boton t-boton-marcacion">
                        <i class="bi bi-fingerprint"></i>
                        <span>Marcación DNI<small>Registrar entrada o salida</small></span>
                    </a>
                @endcan
            </div>
        </section>

        {{-- Información general y accesos rápidos --}}
        <section class="t-tarjeta t-presentacion">
            <div class="t-info-cabecera"><i class="bi bi-info-circle-fill me-2"></i>Sistema de Gestión de Residuos Sólidos Urbanos</div>

            <div class="t-info-cuerpo">
                <figure class="t-info-foto">
                    <img src="{{ asset('images/municipalidad.jpg') }}" alt="Palacio municipal de {{ $municipalidad }}">
                    <figcaption class="t-sello"><i class="bi bi-building-fill me-1"></i> Gobierno Local</figcaption>
                </figure>

                <div>
                    <h2><i class="bi bi-building-fill"></i> {{ $municipalidad }}</h2>
                    <p class="t-descripcion">
                        Sistema integral para la gestión eficiente de los residuos sólidos urbanos del distrito,
                        optimizando rutas de recolección, administrando el personal y mejorando la calidad del
                        servicio hacia la comunidad josefina.
                    </p>

                    <nav class="t-funciones" aria-label="Accesos rápidos">
                        @forelse ($funciones as $funcion)
                            @if ($funcion['ruta'])
                                <a href="{{ $funcion['ruta'] }}" class="t-funcion">
                                    <span class="t-funcion-icono t-{{ $funcion['color'] }}"><i class="bi {{ $funcion['icono'] }}"></i></span>
                                    <span class="t-funcion-texto">{{ $funcion['texto'] }}</span>
                                    <i class="bi bi-chevron-right t-funcion-flecha"></i>
                                </a>
                            @else
                                <span class="t-funcion pronto" aria-disabled="true">
                                    <span class="t-funcion-icono t-{{ $funcion['color'] }}"><i class="bi {{ $funcion['icono'] }}"></i></span>
                                    <span class="t-funcion-texto">{{ $funcion['texto'] }}<small>Próximamente</small></span>
                                </span>
                            @endif
                        @empty
                            <p class="text-muted mb-0"><i class="bi bi-shield-lock me-1"></i> Todavía no tiene módulos asignados. Solicite sus permisos a un administrador del sistema.</p>
                        @endforelse
                    </nav>

                    <div class="t-sellos">
                        <span class="t-sello-eco"><i class="bi bi-leaf-fill me-1"></i> Eco-Friendly</span>
                        <span class="t-sello-sostenible"><i class="bi bi-recycle me-1"></i> Sostenible</span>
                        <span class="t-sello-responsable"><i class="bi bi-heart-fill me-1"></i> Responsable</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
@stop
