@props([
    'name',
    'id',
    'url',
    'placeholder' => 'Buscar...',
    'requerido' => true,
    'inicial' => null,
    'mensaje' => 'Seleccione una opción de la lista.',
    'vacio' => 'No hay registros.',
    'cambiar' => 'Cambiar',
])

{{-- Campo con buscador (ver Tads.buscador): el id elegido viaja en un campo oculto. --}}
<div class="t-buscador" data-tads-buscador
    data-mensaje="{{ $mensaje }}" data-cambiar="{{ $cambiar }}" data-vacio="{{ $vacio }}"
    data-sin-coincidencias="No hay resultados que coincidan con «:texto»."
    data-url="{{ $url }}"
    @if ($inicial) data-inicial="{{ json_encode($inicial) }}" @endif
    {{ $attributes }}>
    <input type="hidden" name="{{ $name }}" value="{{ $inicial['id'] ?? '' }}">
    <div class="t-buscador-entrada">
        <i class="bi bi-search"></i>
        <input type="search" id="{{ $id }}" class="form-control" @required($requerido) autocomplete="off"
            placeholder="{{ $placeholder }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}Lista">
    </div>
    <ul class="t-buscador-lista" id="{{ $id }}Lista" role="listbox" hidden></ul>
</div>
