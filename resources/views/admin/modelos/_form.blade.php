@php($editando = isset($modelo))

<div class="t-alerta-form"></div>

{{-- Los campos con data-verificar se validan contra el servidor mientras se escribe (mismas reglas que al guardar). --}}
<div data-tads-verificar="{{ route('admin.modelos.verificar', $editando ? ['ignorar' => $modelo->id] : []) }}">
    <div class="mb-3">
        <label for="modeloMarca" class="form-label t-obligatorio">Marca</label>
        <div class="t-buscador" data-tads-buscador data-forma="logo"
            data-mensaje="Seleccione una marca de la lista." data-cambiar="Cambiar marca"
            data-vacio="No hay marcas registradas." data-sin-coincidencias="No hay marcas que coincidan con «:texto»."
            data-url="{{ route('admin.marcas.buscar') }}"
            @if ($marcaElegida) data-inicial='@json($marcaElegida)' @endif>
            <input type="hidden" name="brand_id" value="{{ $marcaElegida['id'] ?? '' }}" data-verificar>
            <div class="t-buscador-entrada">
                <i class="bi bi-search"></i>
                <input type="search" id="modeloMarca" class="form-control" required autocomplete="off"
                    placeholder="Escriba el nombre de la marca..."
                    role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="modeloMarcaLista">
            </div>
            <ul class="t-buscador-lista" id="modeloMarcaLista" role="listbox" hidden></ul>
        </div>
        @if ($editando && $modelo->vehiculos()->exists())
            <div class="form-text"><i class="bi bi-info-circle"></i> Este modelo ya tiene vehículos: su marca no se puede cambiar.</div>
        @endif
    </div>

    <div class="mb-3">
        <label for="modeloNombre" class="form-label t-obligatorio">Nombre del modelo</label>
        <input type="text" name="name" id="modeloNombre" class="form-control" maxlength="100" required data-verificar
            placeholder="Ej. Atego, Dyna, FM" value="{{ old('name', $modelo->name ?? '') }}">
    </div>

    <div class="mb-3">
        <label for="modeloCodigo" class="form-label t-obligatorio">Código del modelo</label>
        <input type="text" name="code" id="modeloCodigo" class="form-control text-uppercase" maxlength="30" required data-verificar
            placeholder="Ej. ATEGO-MB" value="{{ old('code', $modelo->code ?? '') }}">
        <div class="form-text">No se repite dentro de la misma marca; otra marca puede usar el mismo código.</div>
    </div>
</div>

<div class="mb-1">
    <label for="modeloDescripcion" class="form-label">Descripción</label>
    <textarea name="description" id="modeloDescripcion" class="form-control" rows="3" maxlength="500"
        placeholder="Describa el modelo">{{ old('description', $modelo->description ?? '') }}</textarea>
</div>

@include('admin.partials.pie-formulario')
