{{--
    Componente: <x-admin.pie-formulario>
    Propósito: Barra de acciones estandarizada para formularios (Guardar / Cancelar), con soporte para modales y navegación directa.
    
    Props:
    - accion / textoGuardar (string, por defecto 'Guardar'): Texto del botón de confirmación o envío.
    - cancelar / textoCancelar (string, por defecto 'Cancelar'): Texto del botón de cancelación.
    - iconoGuardar (string, por defecto 'bi-floppy-fill'): Ícono del botón de envío.
    - iconoCancelar (string, por defecto 'bi-x-lg'): Ícono del botón de cancelación.
    - cerrarModal (bool, por defecto true): Si es true, añade data-bs-dismiss="modal" al botón de cancelación.
    - urlCancelar (string, opcional): Si se define, el botón cancelar es un enlace <a> hacia esa URL.
    - idGuardar (string, opcional): ID DOM del botón guardar.
    - idCancelar (string, opcional): ID DOM del botón cancelar.
    
    Ejemplo de uso:
    <x-admin.pie-formulario accion="Guardar Vehículo" />
    <x-admin.pie-formulario accion="Actualizar" cancelar="Descartar Cambios" :cerrar-modal="false" url-cancelar="{{ route('admin.personal.index') }}" />
--}}

@props([
    'accion' => null,
    'textoGuardar' => 'Guardar',
    'cancelar' => null,
    'textoCancelar' => 'Cancelar',
    'iconoGuardar' => 'bi-floppy-fill',
    'iconoCancelar' => 'bi-x-lg',
    'cerrarModal' => true,
    'urlCancelar' => null,
    'idGuardar' => null,
    'idCancelar' => null,
])

@php
    $etiquetaGuardar = $accion ?? $textoGuardar;
    $etiquetaCancelar = $cancelar ?? $textoCancelar;
@endphp

<div {{ $attributes->merge(['class' => 't-pie-form']) }}>
    @if($urlCancelar)
        <a href="{{ $urlCancelar }}" class="btn t-boton-cancelar" @if($idCancelar) id="{{ $idCancelar }}" @endif>
            <i class="bi {{ $iconoCancelar }} me-1"></i> {{ $etiquetaCancelar }}
        </a>
    @else
        <button type="button" class="btn t-boton-cancelar" @if($cerrarModal) data-bs-dismiss="modal" @endif @if($idCancelar) id="{{ $idCancelar }}" @endif>
            <i class="bi {{ $iconoCancelar }} me-1"></i> {{ $etiquetaCancelar }}
        </button>
    @endif

    <button type="submit" class="btn t-boton" @if($idGuardar) id="{{ $idGuardar }}" @endif>
        <i class="bi {{ $iconoGuardar }} me-1"></i> {{ $etiquetaGuardar }}
    </button>
</div>
