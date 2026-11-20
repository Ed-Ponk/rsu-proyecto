<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="turnoNombre" class="form-label t-obligatorio">Nombre del turno</label>
    <input type="text" name="name" id="turnoNombre" class="form-control" maxlength="50" required
        placeholder="Ingrese el nombre del turno" value="{{ old('name', $turno->name ?? '') }}">
    <div class="form-text">Ejemplo: Mañana, Tarde, Noche.</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label for="turnoInicio" class="form-label t-obligatorio">Hora de inicio</label>
        <input type="time" name="time_start" id="turnoInicio" class="form-control" required
            value="{{ old('time_start', isset($turno) ? substr($turno->time_start, 0, 5) : '') }}">
        <div class="form-text">Formato de 24 horas.</div>
    </div>
    <div class="col-md-6">
        <label for="turnoFin" class="form-label t-obligatorio">Hora de término</label>
        <input type="time" name="time_end" id="turnoFin" class="form-control" required
            value="{{ old('time_end', isset($turno) ? substr($turno->time_end, 0, 5) : '') }}">
        <div class="form-text">Si es menor que la de inicio, el turno termina al día siguiente.</div>
    </div>
</div>

<div class="mb-3">
    <label for="turnoDescripcion" class="form-label">Descripción</label>
    <textarea name="description" id="turnoDescripcion" class="form-control" rows="3" maxlength="500"
        placeholder="Ingrese una descripción del turno (opcional)">{{ old('description', $turno->description ?? '') }}</textarea>
</div>

<div class="t-nota"><i class="bi bi-info-circle-fill text-primary me-1"></i><strong>Nota:</strong> configure los horarios de entrada y salida de este turno. No pueden cruzarse con los de otro turno.</div>

@include('admin.partials.pie-formulario')
