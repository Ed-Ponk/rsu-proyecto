@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="modal-header">
        <h5 class="modal-title">{{ $title }}</h5>
        <button type="button" class="btn-close" aria-label="Cerrar" x-on:click="$dispatch('close')"></button>
    </div>

    <div class="modal-body">
        {{ $content }}
    </div>

    <div class="modal-footer t-pie-form border-0">
        {{ $footer }}
    </div>
</x-modal>
