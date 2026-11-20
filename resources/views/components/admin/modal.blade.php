{{--
    Componente: <x-admin.modal>
    Propósito: Ventana modal estandarizada Bootstrap 5.3 para carga dinámica AJAX o contenido estático.
    
    Props:
    - id (string, por defecto 'ventanaFormulario'): Identificador único del modal en el DOM.
    - titulo (string, opcional): Título mostrado en la cabecera del modal.
    - icono (string, opcional): Ícono de la cabecera (ej. 'bi-truck').
    - tamano (string, por defecto 'modal-lg'): 'modal-sm', 'modal-md', 'modal-lg', 'modal-xl'.
    - estatico (bool, por defecto true): Impide cerrar al hacer clic fuera del modal.
    - centrado (bool, por defecto true): Centra verticalmente el diálogo.
    - scrollable (bool, por defecto true): Permite scroll interno si el formulario es extenso.
    
    Slots:
    - $slot (predeterminado): Contenido inyectado dentro de .modal-body (puede dejarse vacío si se llena por AJAX).
    - $cabecera / $header: Personalización completa del header si se requiere.
    - $pie / $footer / $acciones: Contenedor .modal-footer con botones de acción si no vienen dentro del formulario.
    
    Ejemplo de uso (AJAX dinámico tradicional):
    <x-admin.modal id="ventanaFormulario" tamano="modal-xl" />
    
    Ejemplo de uso (Contenido estático con slots):
    <x-admin.modal id="modalConfirmar" titulo="Confirmar Acción" tamano="modal-sm">
        <p>¿Está seguro de que desea suspender la unidad?</p>
        <x-slot:pie>
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-danger">Confirmar</button>
        </x-slot:pie>
    </x-admin.modal>
--}}

@props([
    'id' => 'ventanaFormulario',
    'titulo' => '',
    'icono' => null,
    'tamano' => 'modal-lg',
    'estatico' => true,
    'centrado' => true,
    'scrollable' => true,
])

<div {{ $attributes->merge(['class' => 'modal fade t-modal']) }}
    id="{{ $id }}"
    tabindex="-1"
    aria-labelledby="{{ $id }}Titulo"
    aria-hidden="true"
    @if($estatico) data-bs-backdrop="static" data-bs-keyboard="false" @endif>
    <div class="modal-dialog {{ $tamano }} {{ $centrado ? 'modal-dialog-centered' : '' }} {{ $scrollable ? 'modal-dialog-scrollable' : '' }}">
        <div class="modal-content">
            @if(isset($cabecera) || isset($header))
                {{ $cabecera ?? $header }}
            @else
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="{{ $id }}Titulo">
                        @if($icono)
                            <i class="bi {{ $icono }}"></i>
                        @endif
                        <span>{{ $titulo }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            @endif

            <div class="modal-body">
                {{ $slot }}
            </div>

            @if(isset($pie) || isset($footer) || isset($acciones))
                <div class="modal-footer bg-light border-top">
                    {{ $pie ?? $footer ?? $acciones }}
                </div>
            @endif
        </div>
    </div>
</div>
