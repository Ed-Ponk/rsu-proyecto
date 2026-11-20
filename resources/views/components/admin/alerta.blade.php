{{--
    Componente: <x-admin.alerta>
    Propósito: Cuadro de notificación y retroalimentación con variantes semánticas y opción de cierre.
    
    Props:
    - tipo / variante (string, por defecto 'info'):
      * 'info' / 'pizarra' / 'azul'  -> Informativa
      * 'success' / 'esmeralda'      -> Éxito / Operación completada
      * 'warning' / 'ambar'          -> Advertencia / Turno / Precaución
      * 'danger' / 'rojo'            -> Error / Bloqueo
    - mensaje (string, opcional): Contenido del mensaje (si se omite, se usa el $slot).
    - titulo (string, opcional): Título destacado en negrita.
    - icono (string, opcional): Si se omite, se resuelve automáticamente según el tipo.
    - cerrable / dismissible (bool, por defecto false): Permite descartar la alerta con una cruz de cierre.
    
    Ejemplo de uso:
    <x-admin.alerta tipo="warning" titulo="Atención" mensaje="El contrato temporal vence en menos de 15 días." cerrable />
    <x-admin.alerta tipo="success">El personal ha sido registrado correctamente.</x-admin.alerta>
--}}

@props([
    'tipo' => null,
    'variante' => null,
    'mensaje' => null,
    'titulo' => null,
    'icono' => null,
    'cerrable' => false,
    'dismissible' => false,
])

@php
    $tema = strtolower($tipo ?? $variante ?? 'info');
    $esCerrable = $cerrable || $dismissible;

    $claseAlert = match($tema) {
        'success', 'esmeralda', 'verde' => 'alert-success',
        'warning', 'ambar', 'amarillo' => 'alert-warning',
        'danger', 'rojo', 'error' => 'alert-danger',
        'pizarra', 'azul', 'info' => 'alert-info',
        default => 'alert-info',
    };

    $iconoAuto = $icono ?? match($tema) {
        'success', 'esmeralda', 'verde' => 'bi-check-circle-fill',
        'warning', 'ambar', 'amarillo' => 'bi-exclamation-triangle-fill',
        'danger', 'rojo', 'error' => 'bi-x-octagon-fill',
        default => 'bi-info-circle-fill',
    };
@endphp

<div {{ $attributes->merge(['class' => "alert {$claseAlert} d-flex align-items-start gap-2 " . ($esCerrable ? 'alert-dismissible fade show' : '')]) }} role="alert">
    @if($iconoAuto)
        <i class="bi {{ $iconoAuto }} fs-5 flex-shrink-0 mt-1"></i>
    @endif

    <div class="flex-grow-1">
        @if($titulo)
            <div class="fw-bold mb-1">{{ $titulo }}</div>
        @endif
        <div>
            {{ $mensaje ?? $slot }}
        </div>
    </div>

    @if($esCerrable)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    @endif
</div>
