{{--
    Componente: <x-admin.kpi-card>
    Propósito: Tarjeta de indicador clave de rendimiento (KPI) para el Centro de Mando Operativo RSU.
    
    Props:
    - titulo (string, obligatorio): Nombre del indicador (ej. 'Flota Operativa').
    - valor (string|numeric, obligatorio): Cifra representativa (ej. '12 / 14', '84.5', '28').
    - icono (string, obligatorio): Clase de ícono Bootstrap Icons (ej. 'bi-truck-front').
    - variante / acento (string, por defecto 'esmeralda'):
      * 'esmeralda' / 'verde' -> Operativo, alta disponibilidad
      * 'ambar' / 'amarillo'  -> Alerta, contratos por vencer
      * 'pizarra' / 'azul'    -> Informativo institucional, capacidad total
      * 'rojo' / 'peligro'    -> Unidades en taller, faltas
      * 'violeta'             -> Turnos nocturnos, categorías especiales
    - subtexto / subtitulo / unidad (string, opcional): Aclaración o unidad (ej. 'Tn capacidad total').
    - progreso (int|float, opcional): Porcentaje de 0 a 100 para barra de progreso inferior.
    - tendencia (string, opcional): Texto de comparación (ej. '+8% hoy').
    - tendenciaPositiva (bool, por defecto true): Determina si la tendencia se muestra favorable.
    - enlace (string, opcional): Si se define, toda la tarjeta funciona como hipervínculo interactivo.
    
    Ejemplo de uso:
    <x-admin.kpi-card
        titulo="Flota Operativa"
        valor="12 / 14"
        icono="bi-truck-front"
        variante="esmeralda"
        subtexto="86% de disponibilidad en campo"
        :progreso="86"
    />
--}}

@props([
    'titulo',
    'valor',
    'icono',
    'variante' => null,
    'acento' => null,
    'subtexto' => null,
    'subtitulo' => null,
    'unidad' => null,
    'progreso' => null,
    'tendencia' => null,
    'tendenciaPositiva' => true,
    'enlace' => null,
])

@php
    $tema = strtolower($variante ?? $acento ?? 'esmeralda');
    $textoSecundario = $subtexto ?? $subtitulo ?? $unidad;

    $colores = match($tema) {
        'esmeralda', 'verde' => [
            'borde' => '#10b981',
            'fondo_icono' => '#ecfdf5',
            'texto_icono' => '#047857',
            'barra' => '#10b981',
        ],
        'ambar', 'amarillo' => [
            'borde' => '#f59e0b',
            'fondo_icono' => '#fffbeb',
            'texto_icono' => '#b45309',
            'barra' => '#f59e0b',
        ],
        'rojo', 'peligro', 'danger' => [
            'borde' => '#ef4444',
            'fondo_icono' => '#fef2f2',
            'texto_icono' => '#b91c1c',
            'barra' => '#ef4444',
        ],
        'violeta' => [
            'borde' => '#8b5cf6',
            'fondo_icono' => '#f5f3ff',
            'texto_icono' => '#6d28d9',
            'barra' => '#8b5cf6',
        ],
        'pizarra', 'azul', 'info' => [
            'borde' => '#0ea5e9',
            'fondo_icono' => '#f0f9ff',
            'texto_icono' => '#0369a1',
            'barra' => '#0ea5e9',
        ],
        default => [
            'borde' => '#10b981',
            'fondo_icono' => '#ecfdf5',
            'texto_icono' => '#047857',
            'barra' => '#10b981',
        ],
    };
@endphp

<div {{ $attributes->merge(['class' => 'card h-100 border shadow-sm transition-all']) }} style="border-left: 4px solid {{ $colores['borde'] }} !important; border-radius: 8px;">
    @if($enlace)
        <a href="{{ $enlace }}" class="text-decoration-none text-reset h-100 d-flex flex-column p-3">
    @else
        <div class="h-100 d-flex flex-column p-3">
    @endif

        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-uppercase fw-semibold tracking-wider text-muted" style="font-size: 0.73rem; letter-spacing: 0.05em;">
                {{ $titulo }}
            </span>
            <div class="rounded-3 d-flex align-items-center justify-content-center"
                 style="width: 38px; height: 38px; background-color: {{ $colores['fondo_icono'] }}; color: {{ $colores['texto_icono'] }}; font-size: 1.15rem;">
                <i class="bi {{ $icono }}"></i>
            </div>
        </div>

        <div class="d-flex align-items-baseline gap-2 my-auto">
            <span class="fw-bold text-dark font-monospace" style="font-size: 1.85rem; line-height: 1.1;">
                {{ $valor }}
            </span>
            @if($tendencia)
                <span class="small fw-semibold {{ $tendenciaPositiva ? 'text-success' : 'text-danger' }}">
                    <i class="bi {{ $tendenciaPositiva ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                    {{ $tendencia }}
                </span>
            @endif
        </div>

        @if($textoSecundario)
            <div class="text-muted mt-2 small" style="font-size: 0.8rem;">
                {{ $textoSecundario }}
            </div>
        @endif

        @if($progreso !== null)
            <div class="progress mt-2" style="height: 5px;" role="progressbar" aria-valuenow="{{ $progreso }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: {{ min(100, max(0, $progreso)) }}%; background-color: {{ $colores['barra'] }};"></div>
            </div>
        @endif

    @if($enlace)
        </a>
    @else
        </div>
    @endif
</div>
