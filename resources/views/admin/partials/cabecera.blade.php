{{-- Encabezado de pantalla: ícono, título, subtítulo y (opcional) botón "Nuevo". --}}
<div class="t-cabecera">
    <div>
        <h1 class="t-titulo">
            <span class="t-icono-titulo"><i class="bi {{ $icono }}"></i></span>
            {{ $titulo }}
        </h1>
        @isset($subtitulo)
            <p class="t-subtitulo">{{ $subtitulo }}</p>
        @endisset
    </div>

    @isset($boton)
        <button type="button" class="btn t-boton" id="botonNuevo"><i class="bi bi-plus-lg me-1"></i> {{ $boton }}</button>
    @endisset
</div>
