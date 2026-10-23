@extends('adminlte::page')

@section('title', 'Inicio')

@section('content')
    <div class="t-inicio t-inicio-operativo">
        <x-admin.cabecera titulo="¡Bienvenido al Sistema RSU!" :subtitulo="$municipalidad" />

        <section class="t-jornada" aria-label="Hora y marcación"
                 data-tads-modulo="inicio" data-url-estado="{{ route('admin.asistencias.marcacion.estado') }}">
            <div class="t-jornada-reloj" role="timer" aria-live="off">
                <span class="t-jornada-etiqueta">Jornada de trabajo</span>
                <strong data-reloj-hora>--:--:--</strong>
                <span><span data-reloj-fecha>&nbsp;</span> · <span data-reloj-turno>Consultando turno...</span></span>
            </div>
            <a href="{{ route('admin.asistencias.marcacion') }}" class="btn t-boton">
                <i class="bi bi-fingerprint me-1" aria-hidden="true"></i> Marcación DNI
            </a>
        </section>

        <section class="t-inicio-herramientas" aria-labelledby="tituloHerramientas">
            <div>
                <h2 id="tituloHerramientas">Herramientas de gestión</h2>
                <p>Consulte sus registros o continúe con una tarea.</p>
                <nav class="t-funciones" aria-label="Accesos rápidos">
                    @foreach ($funciones as $funcion)
                        @if ($funcion['ruta'])
                            <a href="{{ $funcion['ruta'] }}" class="t-funcion">
                                <span class="t-funcion-icono t-{{ $funcion['color'] }}" aria-hidden="true"><i class="bi {{ $funcion['icono'] }}"></i></span>
                                <span class="t-funcion-texto">{{ $funcion['texto'] }}</span>
                                <i class="bi bi-chevron-right t-funcion-flecha" aria-hidden="true"></i>
                            </a>
                        @else
                            <span class="t-funcion pronto" aria-disabled="true">
                                <span class="t-funcion-icono t-{{ $funcion['color'] }}" aria-hidden="true"><i class="bi {{ $funcion['icono'] }}"></i></span>
                                <span class="t-funcion-texto">{{ $funcion['texto'] }}<small>Próximamente</small></span>
                            </span>
                        @endif
                    @endforeach
                </nav>
            </div>
            <figure class="t-inicio-municipalidad">
                <img src="{{ asset('images/municipalidad.jpg') }}" alt="Palacio municipal de {{ $municipalidad }}" loading="lazy">
                <figcaption>Gestión de Residuos Sólidos Urbanos<br>{{ $municipalidad }}</figcaption>
            </figure>
        </section>
    </div>
@stop
