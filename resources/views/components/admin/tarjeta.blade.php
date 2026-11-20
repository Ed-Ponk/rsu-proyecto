{{--
    Componente: <x-admin.tarjeta>
    Propósito: Contenedor estandarizado de tarjeta para tablas de datos, formularios y paneles de métricas.
    
    Props:
    - titulo (string, opcional): Título mostrado en la cabecera de la tarjeta.
    - icono (string, opcional): Clase de ícono Bootstrap Icons (ej. 'bi-table').
    - badge (string, opcional): Texto de insignia rápida en el encabezado.
    - badgeVariante (string, opcional, por defecto 'info'): Variante de color del badge.
    - sinPadding (bool, opcional, por defecto false): Elimina el padding del cuerpo para tablas sangradas.
    - clase (string, opcional): Clases CSS adicionales para la tarjeta.
    
    Slots:
    - $slot (predeterminado): Contenido principal del cuerpo de la tarjeta.
    - $herramientas / $headerActions: Botones o controles ubicados a la derecha del encabezado.
    - $pie / $footer: Pie de página de la tarjeta.
    
    Ejemplo de uso:
    <x-admin.tarjeta titulo="Listado de Vehículos" icono="bi-truck" sin-padding>
        <x-slot:herramientas>
            <button class="btn btn-sm btn-outline-secondary">Actualizar</button>
        </x-slot:herramientas>
        <table class="table t-tabla">...</table>
    </x-admin.tarjeta>
--}}

@props([
    'titulo' => null,
    'icono' => null,
    'badge' => null,
    'badgeVariante' => 'info',
    'sinPadding' => false,
    'clase' => '',
])

<div {{ $attributes->merge(['class' => 't-tarjeta ' . $clase]) }}>
    @if($titulo || $icono || isset($herramientas) || isset($headerActions))
        <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between px-3 py-2">
            <div class="d-flex align-items-center gap-2">
                @if($icono)
                    <i class="bi {{ $icono }} text-muted"></i>
                @endif
                @if($titulo)
                    <span class="fw-semibold text-dark">{{ $titulo }}</span>
                @endif
                @if($badge)
                    <span class="badge bg-{{ $badgeVariante }} ms-1" style="font-size: 0.72rem;">{{ $badge }}</span>
                @endif
            </div>
            @if(isset($herramientas) || isset($headerActions))
                <div class="d-flex align-items-center gap-2">
                    {{ $herramientas ?? $headerActions }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $sinPadding ? 'p-0' : 't-tarjeta-cuerpo' }}">
        {{ $slot }}
    </div>

    @if(isset($pie) || isset($footer))
        <div class="card-footer bg-light border-top px-3 py-2 text-muted" style="font-size: 0.82rem;">
            {{ $pie ?? $footer }}
        </div>
    @endif
</div>
