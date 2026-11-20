<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="motivoNombre" class="form-label t-obligatorio">Nombre del motivo</label>
    <input type="text" name="name" id="motivoNombre" class="form-control" maxlength="100" required
        placeholder="Ejemplo: Personal faltante" value="{{ old('name', $motivo->name ?? '') }}">
</div>

<div class="mb-3">
    <label for="motivoDescripcion" class="form-label">Descripción</label>
    <textarea name="description" id="motivoDescripcion" class="form-control" rows="3" maxlength="500"
        placeholder="Explique cuándo se usa este motivo (opcional)">{{ old('description', $motivo->description ?? '') }}</textarea>
</div>

<div class="t-nota"><i class="bi bi-info-circle-fill text-primary me-1"></i><strong>Nota:</strong> estos motivos aparecen en la lista al modificar una programación y al hacer un cambio masivo.</div>

@include('admin.partials.pie-formulario')
