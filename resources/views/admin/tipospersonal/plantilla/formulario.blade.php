@php($esDelSistema = isset($tipo) && $tipo->is_system)

<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="tipoPersonalNombre" class="form-label t-obligatorio">Nombre</label>
    <input type="text" name="name" id="tipoPersonalNombre" class="form-control" maxlength="100" required
        placeholder="Nombre de la función" value="{{ old('name', $tipo->name ?? '') }}" @readonly($esDelSistema)>
    @if ($esDelSistema)
        <div class="form-text"><i class="bi bi-lock-fill"></i> Es una función predefinida: el nombre no se puede cambiar.</div>
    @endif
</div>

<div class="mb-1">
    <label for="tipoPersonalDescripcion" class="form-label">Descripción</label>
    <textarea name="description" id="tipoPersonalDescripcion" class="form-control" rows="3" maxlength="255"
        placeholder="Describa la función (opcional)">{{ old('description', $tipo->description ?? '') }}</textarea>
</div>

@include('admin.partials.pie-formulario')
