{{-- Ventana emergente reutilizable: el formulario llega por AJAX y se coloca dentro de .modal-body. --}}
<div class="modal fade t-modal" id="ventanaFormulario" data-bs-backdrop="static" tabindex="-1"
    aria-labelledby="tituloVentana" aria-hidden="true">
    <div class="modal-dialog {{ $tamano ?? 'modal-lg' }} modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloVentana"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>
