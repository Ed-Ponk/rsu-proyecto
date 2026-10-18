{{--
    Componente: <x-admin.boton>
    Propósito: Botón de acción estandarizado para la suite municipal con soporte para variantes, íconos y enlaces.
    
    Props:
    - variante (string, por defecto 'primary'):
      * 'primary'   -> Botón principal municipal (celeste/azul acción .t-boton)
      * 'secondary' -> Botón neutro/secundario (.btn-secondary)
      * 'danger' / 'cancel' -> Botón de cancelación / eliminación (.t-boton-cancelar)
      * 'warning'   -> Botón de advertencia o edición (.btn-warning)
      * 'success'   -> Botón de éxito o confirmación (.btn-success)
      * 'ghost'     -> Botón sin fondo / plano (.btn-link)
      * 'icon'      -> Botón cuadrado compacto para acciones de fila (.t-boton-icono)
    - icono (string, opcional): Clase de ícono Bootstrap Icons (ej. 'bi-plus-lg', 'bi-pencil-fill').
    - texto (string, opcional): Texto visible del botón (si se omite, se usa el $slot).
    - tipo (string, por defecto 'button'): 'button', 'submit', 'reset'.
    - href (string, opcional): Si se define, renderiza una etiqueta <a> en lugar de <button>.
    - tamano (string, opcional): 'sm', 'lg'.
    - disabled (bool, por defecto false): Deshabilita el elemento.
    
    Ejemplo de uso:
    <x-admin.boton variante="primary" icono="bi-plus-lg" texto="Registrar Personal" />
    <x-admin.boton variante="danger" icono="bi-trash" tipo="button" id="btnEliminar" />
    <x-admin.boton href="{{ route('admin.vehiculos.index') }}" variante="secondary" icono="bi-arrow-left">Volver</x-admin.boton>
--}}

@props([
    'variante' => 'primary',
    'icono' => null,
    'texto' => null,
    'tipo' => 'button',
    'href' => null,
    'tamano' => null,
    'disabled' => false,
])

@php
    $claseVariante = match(strtolower($variante)) {
        'primary' => 'btn t-boton',
        'secondary' => 'btn btn-secondary',
        'danger', 'cancel' => 'btn t-boton-cancelar',
        'warning' => 'btn btn-warning text-dark',
        'success' => 'btn btn-success',
        'ghost' => 'btn btn-link text-decoration-none',
        'icon' => 't-boton-icono',
        default => 'btn t-boton',
    };

    $claseTamano = match($tamano) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };

    $clases = trim("{$claseVariante} {$claseTamano}");
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases]) }} @if($disabled) aria-disabled="true" tabindex="-1" style="pointer-events: none; opacity: 0.65;" @endif>
        @if($icono)
            <i class="bi {{ $icono }} {{ ($texto || $slot->isNotEmpty()) ? 'me-1' : '' }}"></i>
        @endif
        {{ $texto ?? $slot }}
    </a>
@else
    <button type="{{ $tipo }}" {{ $attributes->merge(['class' => $clases]) }} @disabled($disabled)>
        @if($icono)
            <i class="bi {{ $icono }} {{ ($texto || $slot->isNotEmpty()) ? 'me-1' : '' }}"></i>
        @endif
        {{ $texto ?? $slot }}
    </button>
@endif
