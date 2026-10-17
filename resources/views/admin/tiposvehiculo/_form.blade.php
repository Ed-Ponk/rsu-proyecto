<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="tipoNombre" class="form-label t-obligatorio">Nombre</label>
    <input type="text" name="name" id="tipoNombre" class="form-control" maxlength="100" required
        placeholder="Ej. Camión Recolector" value="{{ old('name', $tipo->name ?? '') }}">
</div>

<div class="mb-1">
    <label for="tipoDescripcion" class="form-label">Descripción</label>
    <textarea name="description" id="tipoDescripcion" class="form-control" rows="3" maxlength="500"
        placeholder="Describa el tipo de vehículo">{{ old('description', $tipo->description ?? '') }}</textarea>
</div>

@include('admin.partials.pie-formulario')
