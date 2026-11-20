<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="feriadoFecha" class="form-label t-obligatorio">Fecha del feriado</label>
    <input type="date" name="date" id="feriadoFecha" class="form-control" required
        value="{{ old('date', isset($feriado) ? $feriado->date->toDateString() : '') }}">
    <div class="form-text">Día: <strong id="feriadoDia" class="text-primary">Seleccione una fecha</strong></div>
</div>

<div class="mb-3">
    <label for="feriadoDescripcion" class="form-label t-obligatorio">Descripción</label>
    <input type="text" name="description" id="feriadoDescripcion" class="form-control" maxlength="150" required
        placeholder="Descripción del día feriado" value="{{ old('description', $feriado->description ?? '') }}">
</div>

@php($estadoActual = (string) old('status', isset($feriado) ? (int) $feriado->status : 1))
<div class="mb-3">
    <label for="feriadoEstado" class="form-label">Estado</label>
    <select name="status" id="feriadoEstado" class="form-select">
        <option value="1" @selected($estadoActual === '1')>Activo</option>
        <option value="0" @selected($estadoActual === '0')>Inactivo</option>
    </select>
    <div class="form-text">Los feriados inactivos no se considerarán en las validaciones de programación.</div>
</div>

<div class="t-nota">
    <strong><i class="bi bi-info-circle-fill text-primary me-1"></i> Información:</strong>
    <ul>
        <li>Los días feriados afectan la programación de rutas.</li>
        <li>Puede cargar los feriados oficiales con el botón «Cargar Feriados Perú».</li>
        <li>Los feriados inactivos no se consideran en las validaciones.</li>
    </ul>
</div>

@include('admin.partials.pie-formulario')
