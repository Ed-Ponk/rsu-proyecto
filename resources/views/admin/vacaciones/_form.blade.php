@php($editando = isset($vacacion))

<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-md-6">
        <label for="vacacionPersona" class="form-label t-obligatorio">Personal</label>
        @if ($editando)
            <input type="text" id="vacacionPersona" class="form-control" readonly
                value="{{ $vacacion->personal->nombre_completo }} — DNI {{ $vacacion->personal->dni }}">
        @else
            <div class="t-buscador" data-tads-buscador
                data-mensaje="Seleccione a una persona de la lista." data-cambiar="Cambiar persona"
                data-vacio="No hay personal activo registrado." data-sin-coincidencias="No hay personal activo que coincida con «:texto»."
                data-url="{{ route('admin.personal.buscar', ['contexto' => 'vacaciones']) }}">
                <input type="hidden" name="user_id" value="">
                <div class="t-buscador-entrada">
                    <i class="bi bi-search"></i>
                    <input type="search" id="vacacionPersona" class="form-control" required autocomplete="off"
                        placeholder="Buscar empleado por nombre o DNI"
                        role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="vacacionPersonaLista">
                </div>
                <ul class="t-buscador-lista" id="vacacionPersonaLista" role="listbox" hidden></ul>
            </div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="vacacionDias" class="form-label t-obligatorio">Días solicitados</label>
        <input type="number" name="days" id="vacacionDias" class="form-control" required min="1" max="{{ $cupo }}" step="1"
            placeholder="Número de días" value="{{ old('days', $vacacion->days ?? '') }}">
    </div>

    <div class="col-md-6">
        <label for="vacacionInicio" class="form-label t-obligatorio">Fecha de inicio</label>
        <input type="date" name="start_date" id="vacacionInicio" class="form-control" required min="{{ today()->toDateString() }}"
            value="{{ old('start_date', isset($vacacion) ? $vacacion->start_date->toDateString() : '') }}">
    </div>

    <div class="col-md-6">
        <label for="vacacionFin" class="form-label">Fecha de fin</label>
        <input type="date" id="vacacionFin" class="form-control" readonly tabindex="-1"
            value="{{ isset($vacacion) ? $vacacion->end_date->toDateString() : '' }}">
        <div class="form-text">Se calcula con la fecha de inicio y los días solicitados.</div>
    </div>

    <div class="col-12">
        <label for="vacacionNotas" class="form-label">Notas</label>
        <textarea name="notes" id="vacacionNotas" class="form-control" rows="3" maxlength="500"
            placeholder="Observaciones o comentarios sobre la solicitud...">{{ old('notes', $vacacion->notes ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="t-nota t-nota-ambar">
            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Importante:</strong>
            <ul>
                <li>Solo el personal <strong>nombrado</strong> y de contrato <strong>permanente</strong> puede solicitar vacaciones.</li>
                <li>No se pueden solicitar vacaciones en fechas que coincidan con otras solicitudes aprobadas o pendientes.</li>
                <li>Las solicitudes pendientes pueden ser editadas o eliminadas.</li>
                <li>Cada persona dispone de {{ $cupo }} días por año; solo las solicitudes aprobadas los descuentan.</li>
            </ul>
        </div>
    </div>
</div>

@include('admin.partials.pie-formulario')
