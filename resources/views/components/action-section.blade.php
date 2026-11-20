@props(['icono' => 'bi-gear', 'peligro' => false])

<section {{ $attributes->merge(['class' => 't-tarjeta t-seccion-perfil mb-3'.($peligro ? ' t-seccion-peligro' : '')]) }}>
    <x-section-title :icono="$icono">
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-section-title>

    <div class="t-tarjeta-cuerpo">
        {{ $content }}
    </div>
</section>
