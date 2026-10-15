@php
    $editando = isset($asistencia);
    $ahora = now();
    $textoTipo = $editando
        ? match ($asistencia->type) { 'entrada' => 'Entrada', 'salida' => 'Salida', default => 'No aplica (ausencia)' }
        : 'Automático';
@endphp

<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-12">
        <label for="asistenciaPersona" class="form-label t-obligatorio">Empleado</label>
        @if ($editando)
            <input type="text" id="asistenciaPersona" class="form-control" readonly
                value="{{ $asistencia->personal->nombre_completo }} — DNI {{ $asistencia->personal->dni }}">
            <input type="hidden" id="personaEnEdicion" value="{{ $asistencia->user_id }}">
        @else
            <select name="user_id" id="asistenciaPersona" class="form-select" required>
                <option value="">Seleccione al personal</option>
                @foreach ($personal as $persona)
                    <option value="{{ $persona->id }}" @selected((int) old('user_id') === $persona->id)>
                        {{ $persona->nombre_completo }} — DNI {{ $persona->dni }} ({{ $persona->tipo?->name }})
                    </option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="col-12"><div id="fichaPersona" class="t-ficha-persona d-none"></div></div>

    <div class="col-md-4">
        <label for="asistenciaFecha" class="form-label t-obligatorio">Fecha</label>
        <input type="date" name="date" id="asistenciaFecha" class="form-control" required max="{{ $ahora->toDateString() }}"
            value="{{ old('date', $editando ? $asistencia->date->toDateString() : $ahora->toDateString()) }}">
    </div>
    <div class="col-md-4">
        <label for="asistenciaHora" class="form-label t-obligatorio">Hora</label>
        <input type="time" name="time" id="asistenciaHora" class="form-control" required
            value="{{ old('time', $editando ? substr($asistencia->time, 0, 5) : $ahora->format('H:i')) }}">
    </div>
    <div class="col-md-4">
        <label for="asistenciaTurno" class="form-label">Turno</label>
        <input type="text" id="asistenciaTurno" class="form-control" readonly
            value="{{ $editando ? ($asistencia->turno?->etiqueta ?? 'Sin turno') : '' }}">
        <div class="form-text">Se asigna solo, según la hora.</div>
    </div>

    <div class="col-md-6">
        <label for="asistenciaTipo" class="form-label">Tipo</label>
        <input type="text" id="asistenciaTipo" class="form-control" readonly value="{{ $textoTipo }}" data-actual="{{ $editando ? $textoTipo : '' }}">
        <div class="form-text">
            <i class="bi bi-magic"></i>
            {{ $editando ? 'Se recalcula según el orden de las marcaciones del día.' : 'La primera marcación del día es entrada; luego se alterna con salida.' }}
        </div>
    </div>
    <div class="col-md-6">
        <label for="asistenciaEstado" class="form-label t-obligatorio">Estado</label>
        @php($estadoActual = old('status', $asistencia->status ?? 'presente'))
        <select name="status" id="asistenciaEstado" class="form-select" required>
            <option value="presente" @selected($estadoActual === 'presente')>Presente</option>
            <option value="ausente" @selected($estadoActual === 'ausente')>Ausente</option>
        </select>
    </div>

    <div class="col-12">
        <label for="asistenciaNotas" class="form-label">Notas</label>
        <textarea name="notes" id="asistenciaNotas" class="form-control" rows="2" maxlength="500"
            placeholder="Observaciones sobre el registro">{{ old('notes', $asistencia->notes ?? '') }}</textarea>
    </div>
</div>

@include('admin.partials.pie-formulario')
