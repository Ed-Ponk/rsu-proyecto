@props(['submit', 'icono' => 'bi-gear'])

<section {{ $attributes->merge(['class' => 't-tarjeta t-seccion-perfil mb-3']) }}>
    <x-section-title :icono="$icono">
        <x-slot name="title">{{ $title }}</x-slot>
        <x-slot name="description">{{ $description }}</x-slot>
    </x-section-title>

    <form wire:submit="{{ $submit }}" class="t-formulario">
        <div class="t-tarjeta-cuerpo">
            <div class="row g-3">
                {{ $form }}
            </div>
        </div>

        @if (isset($actions))
            <div class="t-pie-perfil">
                {{ $actions }}
            </div>
        @endif
    </form>
</section>
