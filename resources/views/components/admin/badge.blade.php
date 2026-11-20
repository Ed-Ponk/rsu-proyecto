{{--
    Componente: <x-admin.badge>
    Propósito: Insignia de estado semántica con paleta municipal de alto contraste y legibilidad.
    
    Props:
    - variante (string, por defecto 'info'):
      * 'activo' / 'success' / 'esmeralda' / 'verde'  -> Operativo / Vigente
      * 'inactivo' / 'danger' / 'rojo'               -> Inactivo / Vencido / Peligro
      * 'por-vencer' / 'warning' / 'ambar'           -> Alerta / Próximo a vencer / Turno
      * 'info' / 'neutral' / 'pizarra' / 'azul'      -> Neutro / Informativo
      * 'violeta' / 'turquesa' / 'gris'              -> Categorías secundarias
    - texto (string, opcional): Contenido del badge (si se omite, se usa el $slot).
    - dot (bool, por defecto false): Muestra un punto indicador circular de estado en vivo.
    - icono (string, opcional): Ícono complementario dentro de la insignia.
    - tamano (string, por defecto 'md'): 'sm', 'md', 'lg'.
    
    Ejemplo de uso:
    <x-admin.badge variante="activo" dot texto="Vigente" />
    <x-admin.badge variante="por-vencer" icono="bi-clock-history">Por Vencer (15d)</x-admin.badge>
--}}

@props([
    'variante' => 'info',
    'texto' => null,
    'dot' => false,
    'icono' => null,
    'tamano' => 'md',
])

@php
    $claseVariante = match(strtolower($variante)) {
        'activo', 'success', 'verde', 'esmeralda' => 't-verde',
        'inactivo', 'danger', 'rojo', 'eliminado' => 't-rojo',
        'por-vencer', 'warning', 'ambar', 'amarillo', 'alerta' => 't-ambar',
        'azul', 'pizarra', 'info' => 't-azul',
        'violeta' => 't-violeta',
        'turquesa' => 't-turquesa',
        'gris', 'neutral', 'secondary' => 't-gris',
        default => 't-azul',
    };

    $estiloTamano = match($tamano) {
        'sm' => 'font-size: 0.68rem; padding: 2px 6px;',
        'lg' => 'font-size: 0.85rem; padding: 5px 12px;',
        default => '',
    };
@endphp

<span {{ $attributes->merge(['class' => "t-insignia {$claseVariante}"]) }} @if($estiloTamano) style="{{ $estiloTamano }}" @endif>
    @if($dot)
        <span class="d-inline-block rounded-circle me-1" style="width: 6px; height: 6px; background-color: currentColor; vertical-align: middle;"></span>
    @endif
    @if($icono)
        <i class="bi {{ $icono }} me-1" style="font-size: 0.9em;"></i>
    @endif
    {{ $texto ?? $slot }}
</span>
