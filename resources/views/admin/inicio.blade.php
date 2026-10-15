@extends('adminlte::page')

@section('title', 'Inicio')

@section('content')
    <div class="t-inicio">
        {{-- Banner de bienvenida --}}
        <section class="t-banner">
            <h1><i class="bi bi-star-fill"></i> ¡Bienvenido al Sistema RSU!</h1>
            <p>Sistema de Gestión de Residuos Sólidos Urbanos - {{ $municipalidad }}</p>
            <small><i class="bi bi-info-circle-fill me-1"></i> Utilice el menú lateral para navegar entre los diferentes módulos del sistema</small>
        </section>

        {{-- Información general y accesos rápidos --}}
        <section class="t-info">
            <div class="t-info-cabecera"><i class="bi bi-info-circle-fill me-1"></i> Sistema de Gestión de Residuos Sólidos Urbanos</div>

            <div class="t-info-cuerpo">
                <div>
                    <img class="t-foto-edificio" src="{{ asset('images/municipalidad.jpg') }}" alt="Palacio municipal">
                    <span class="t-sello"><i class="bi bi-building-fill me-1"></i> Gobierno Local</span>
                </div>

                <div>
                    <h2><i class="bi bi-building-fill"></i> {{ $municipalidad }}</h2>
                    <p class="t-descripcion">
                        Sistema integral para la gestión eficiente de los residuos sólidos urbanos del distrito,
                        optimizando rutas de recolección, administrando el personal y mejorando la calidad del
                        servicio hacia la comunidad josefina.
                    </p>

                    <div class="t-funciones">
                        @foreach ($funciones as $funcion)
                            <a href="{{ $funcion['ruta'] ?? '#' }}" @class(['t-funcion', 'pronto' => ! $funcion['ruta']])>
                                <i class="bi {{ $funcion['icono'] }}" style="color: {{ $funcion['color'] }}"></i>
                                <span>{{ $funcion['texto'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    <div class="t-sellos">
                        <span class="eco"><i class="bi bi-leaf-fill me-1"></i> Eco-Friendly</span>
                        <span class="sostenible"><i class="bi bi-recycle me-1"></i> Sostenible</span>
                        <span class="responsable"><i class="bi bi-heart-fill me-1"></i> Responsable</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
@stop
