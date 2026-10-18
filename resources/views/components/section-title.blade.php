@props(['icono' => 'bi-gear'])

<div class="t-perfil-cabecera">
    <span class="t-perfil-icono"><i class="bi {{ $icono }}"></i></span>
    <div>
        <h2 class="t-perfil-titulo">{{ $title }}</h2>
        <p class="t-perfil-descripcion">{{ $description }}</p>
    </div>
</div>
