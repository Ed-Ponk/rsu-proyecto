{{--
    Buscador con sugerencias del servidor (Tads.buscador). Reemplaza a los combos cuyos datos salen de otros CRUD,
    porque esas listas crecen con el uso y un combo largo es incómodo.

    Parámetros:
      id          id del campo de texto (el id del campo oculto es "{id}Id")
      nombre      name del campo oculto que viaja al servidor (ej. type_id)
      url         endpoint que responde { data: [...] }
      placeholder texto de ayuda del campo
      elegido     (opcional) valor ya elegido, con la misma forma que devuelve el buscador
      forma       (opcional) 'avatar' | 'logo'
      mensaje, cambiar, vacio, sinCoincidencias   (opcionales) textos del buscador
      depende     (opcional) selector del campo oculto de otro buscador del que depende este (ej. '#vehiculoMarcaId')
      parametro   (opcional) nombre con que se envía ese valor en la búsqueda (ej. 'marca')
      sinDependencia (opcional) texto que se muestra mientras no se elige aquello de lo que depende
--}}
<div class="t-buscador" data-tads-buscador
    @isset($forma) data-forma="{{ $forma }}" @endisset
    data-mensaje="{{ $mensaje ?? 'Seleccione una opción de la lista.' }}" data-cambiar="{{ $cambiar ?? 'Cambiar' }}"
    data-vacio="{{ $vacio ?? 'No hay registros.' }}"
    data-sin-coincidencias="{{ $sinCoincidencias ?? 'No hay resultados que coincidan con «:texto».' }}"
    data-url="{{ $url }}"
    @isset($depende) data-depende="{{ $depende }}" data-parametro="{{ $parametro }}" data-sin-dependencia="{{ $sinDependencia ?? '' }}" @endisset
    @if (! empty($elegido)) data-inicial='@json($elegido)' @endif>
    <input type="hidden" name="{{ $nombre }}" id="{{ $id }}Id" value="{{ $elegido['id'] ?? '' }}">
    <div class="t-buscador-entrada">
        <i class="bi bi-search"></i>
        <input type="search" id="{{ $id }}" class="form-control" required autocomplete="off"
            placeholder="{{ $placeholder }}"
            role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}Lista">
    </div>
    <ul class="t-buscador-lista" id="{{ $id }}Lista" role="listbox" hidden></ul>
</div>
