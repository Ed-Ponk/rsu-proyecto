@props(['id', 'maxWidth'])

@php
$id = $id ?? md5($attributes->wire('model'));

$ancho = [
    'sm' => '380px',
    'md' => '480px',
    'lg' => '620px',
    'xl' => '820px',
    '2xl' => '560px',
][$maxWidth ?? '2xl'];
@endphp

{{-- Ventana emergente con la misma apariencia que las del panel; se abre y cierra desde Livewire (wire:model). --}}
<div
    x-data="{ show: @entangle($attributes->wire('model')) }"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    id="{{ $id }}"
    class="t-ventana-flotante"
    style="display: none;"
>
    <div x-show="show" class="t-ventana-fondo" x-on:click="show = false"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

    <div x-show="show" class="t-ventana-caja t-modal" style="max-width: {{ $ancho }}"
         x-trap.inert.noscroll="show"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>
