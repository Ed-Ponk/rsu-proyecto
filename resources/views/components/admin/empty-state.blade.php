{{--
    Componente: <x-admin.empty-state>
    Propósito: Estado vacío expresivo y humanizado para listas, tablas y galerías sin registros.
    
    Props:
    - icono (string, por defecto 'bi-inbox'): Clase de ícono Bootstrap Icons complementario.
    - titulo (string, por defecto 'No hay registros disponibles'): Título empático.
    - descripcion (string, por defecto 'No se encontraron elementos para mostrar en este momento.'): Orientación al usuario.
    - accion / accionTexto (string, opcional): Texto del botón de acción para invocar creación o restablecimiento.
    - accionUrl (string, opcional): Enlace si la acción es navegación directa.
    - accionId (string, opcional): ID DOM para interactuar con JavaScript (ej. 'botonNuevo').
    - accionIcono (string, por defecto 'bi-plus-lg'): Ícono del botón de acción.
    - svg (bool, por defecto true): Muestra la ilustración SVG municipal ambiental sutil.
    
    Slots:
    - $slot / $acciones: Controles o botones de acción personalizados.
    
    Ejemplo de uso:
    <x-admin.empty-state
        icono="bi-truck"
        titulo="Sin vehículos registrados"
        descripcion="Actualmente no hay unidades vehiculares en este sector. Comience registrando la primera unidad de recolección."
        accion="Registrar primer vehículo"
        accion-id="botonNuevo"
    />
--}}

@props([
    'icono' => 'bi-inbox',
    'titulo' => 'No hay registros disponibles',
    'descripcion' => 'No se encontraron elementos para mostrar en este momento.',
    'accion' => null,
    'accionTexto' => null,
    'accionUrl' => null,
    'accionId' => null,
    'accionIcono' => 'bi-plus-lg',
    'svg' => true,
])

@php
    $textoBoton = $accion ?? $accionTexto;
@endphp

<div {{ $attributes->merge(['class' => 'text-center py-5 px-3 rounded-3 bg-white border border-dashed my-3']) }} style="border-color: #cbd5e1 !important; border-width: 2px !important;">
    <div class="mx-auto mb-3" style="max-width: 120px;">
        @if($svg)
            {{-- Ilustración SVG sobria y artesanal de gestión municipal ambiental --}}
            <svg class="w-100 h-auto" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" style="max-height: 90px;">
                <circle cx="60" cy="60" r="50" fill="#f1f5f9" stroke="#e2e8f0" stroke-width="2"/>
                <path d="M40 70C40 58.9543 48.9543 50 60 50C71.0457 50 80 58.9543 80 70H40Z" fill="#10b981" fill-opacity="0.15"/>
                <rect x="44" y="58" width="32" height="24" rx="4" fill="#ffffff" stroke="#64748b" stroke-width="2"/>
                <path d="M48 66H72" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                <path d="M52 72H68" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                <circle cx="60" cy="42" r="10" fill="#047857" fill-opacity="0.1" stroke="#047857" stroke-width="2"/>
                <path d="M60 38V46M56 42H64" stroke="#047857" stroke-width="2" stroke-linecap="round"/>
            </svg>
        @else
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-muted" style="width: 72px; height: 72px; font-size: 2rem;">
                <i class="bi {{ $icono }}"></i>
            </div>
        @endif
    </div>

    <h5 class="fw-semibold text-dark mb-1" style="font-size: 1.1rem;">
        {{ $titulo }}
    </h5>

    <p class="text-muted mx-auto mb-3" style="max-width: 440px; font-size: 0.88rem; line-height: 1.5;">
        {{ $descripcion }}
    </p>

    @if(isset($acciones))
        <div class="mt-2">
            {{ $acciones }}
        </div>
    @elseif($slot->isNotEmpty())
        <div class="mt-2">
            {{ $slot }}
        </div>
    @elseif($textoBoton)
        <div class="mt-2">
            @if($accionUrl)
                <a href="{{ $accionUrl }}" class="btn t-boton" @if($accionId) id="{{ $accionId }}" @endif>
                    <i class="bi {{ $accionIcono }} me-1"></i> {{ $textoBoton }}
                </a>
            @else
                <button type="button" class="btn t-boton" @if($accionId) id="{{ $accionId }}" @endif>
                    <i class="bi {{ $accionIcono }} me-1"></i> {{ $textoBoton }}
                </button>
            @endif
        </div>
    @endif
</div>
