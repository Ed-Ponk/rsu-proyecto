{{--
    Componente: <x-admin.cabecera>
    Propósito: Encabezado institucional estandarizado para módulos administrativos y centro de mando.
    
    Props:
    - titulo (string, obligatorio): Título principal del módulo.
    - subtitulo (string, opcional): Descripción o instrucción secundaria.
    - icono (string, opcional): Clase de ícono Bootstrap Icons (ej. 'bi-truck-front').
    - breadcrumb (array, opcional): Lista asociativa de migas de pan ['Etiqueta' => 'url', 'Actual' => null].
    - boton (string, opcional): Texto del botón de acción principal (ej. 'Nuevo vehículo').
    - botonIcono (string, opcional, por defecto 'bi-plus-lg'): Ícono para el botón.
    - botonId (string, opcional, por defecto 'botonNuevo'): ID DOM del botón.
    - botonUrl (string, opcional): Si se define, renderiza un <a> en lugar de <button>.
    
    Slots:
    - $slot / $acciones: Botones de acción personalizados o controles de filtro adicionales.
    
    Ejemplo de uso:
    <x-admin.cabecera
        titulo="Gestión de Vehículos"
        subtitulo="Supervisión de flota y estado operativo de unidades"
        icono="bi-truck-front"
        boton="Nuevo vehículo"
    />
--}}

@props([
    'titulo',
    'subtitulo' => null,
    'icono' => null,
    'breadcrumb' => [],
    'boton' => null,
    'botonIcono' => 'bi-plus-lg',
    'botonId' => 'botonNuevo',
    'botonUrl' => null,
])

<div {{ $attributes->merge(['class' => 't-cabecera']) }}>
    <div>
        @if(!empty($breadcrumb))
            <nav aria-label="breadcrumb" class="mb-1">
                <ol class="breadcrumb mb-0 py-0" style="font-size: 0.78rem;">
                    @foreach($breadcrumb as $etiqueta => $url)
                        @if($url && !$loop->last)
                            <li class="breadcrumb-item"><a href="{{ $url }}" class="text-decoration-none text-muted">{{ $etiqueta }}</a></li>
                        @else
                            <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">{{ $etiqueta }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        <h1 class="t-titulo">
            @if($icono)
                <span class="t-icono-titulo"><i class="bi {{ $icono }}"></i></span>
            @endif
            <span>{{ $titulo }}</span>
        </h1>

        @if($subtitulo)
            <p class="t-subtitulo">{{ $subtitulo }}</p>
        @endif
    </div>

    <div class="d-flex align-items-center gap-2">
        @if(isset($acciones))
            {{ $acciones }}
        @elseif($slot->isNotEmpty())
            {{ $slot }}
        @elseif($boton)
            @if($botonUrl)
                <a href="{{ $botonUrl }}" class="btn t-boton" id="{{ $botonId }}">
                    <i class="bi {{ $botonIcono }} me-1"></i> {{ $boton }}
                </a>
            @else
                <button type="button" class="btn t-boton" id="{{ $botonId }}">
                    <i class="bi {{ $botonIcono }} me-1"></i> {{ $boton }}
                </button>
            @endif
        @endif
    </div>
</div>
