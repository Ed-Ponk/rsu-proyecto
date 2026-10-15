@php($editando = isset($contrato))

<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-12">
        <label for="contratoPersona" class="form-label t-obligatorio">Personal</label>
        @if ($editando)
            <input type="text" id="contratoPersona" class="form-control" readonly
                value="{{ $contrato->personal->nombre_completo }} — DNI {{ $contrato->personal->dni }}">
        @else
            <select name="user_id" id="contratoPersona" class="form-select" required>
                <option value="">Seleccione a la persona</option>
                @foreach ($personal as $persona)
                    <option value="{{ $persona->id }}" @selected((int) old('user_id') === $persona->id)>
                        {{ $persona->nombre_completo }} — {{ $persona->dni }} ({{ $persona->tipo?->name }})
                    </option>
                @endforeach
            </select>
            <div class="form-text">Solo aparece el personal activo. Una persona no puede tener más de un contrato activo a la vez.</div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="contratoTipo" class="form-label t-obligatorio">Tipo de contrato</label>
        <select name="type" id="contratoTipo" class="form-select" required>
            @foreach ($tipos as $valor => $etiquetaTipo)
                <option value="{{ $valor }}" @selected(old('type', $contrato->type ?? 'permanente') === $valor)>{{ $etiquetaTipo }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label for="contratoSalario" class="form-label t-obligatorio">Salario</label>
        <div class="input-group">
            <span class="input-group-text">S/</span>
            <input type="number" step="0.01" min="0.01" max="99999.99" name="salary" id="contratoSalario" class="form-control" required
                placeholder="0.00" value="{{ old('salary', $contrato->salary ?? '') }}">
        </div>
    </div>

    <div class="col-md-6">
        <label for="contratoInicio" class="form-label t-obligatorio">Fecha de inicio</label>
        <input type="date" name="start_date" id="contratoInicio" class="form-control" required
            value="{{ old('start_date', $editando ? $contrato->start_date->toDateString() : '') }}">
    </div>
    <div class="col-md-6">
        <label for="contratoFin" class="form-label">Fecha de finalización</label>
        <input type="date" name="end_date" id="contratoFin" class="form-control"
            value="{{ old('end_date', $editando ? $contrato->end_date?->toDateString() : '') }}">
        <div class="form-text" id="ayudaFin"></div>
    </div>

    <div class="col-md-6">
        <label for="contratoPrueba" class="form-label">Período de prueba (meses)</label>
        <input type="number" min="1" max="12" name="probation_months" id="contratoPrueba" class="form-control"
            placeholder="Opcional" value="{{ old('probation_months', $contrato->probation_months ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label d-block">¿Contrato activo?</label>
        <div class="form-check form-switch fs-5">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="contratoActivo" value="1"
                @checked(old('is_active', $contrato->is_active ?? true))>
            <label class="form-check-label fs-6 fw-semibold" for="contratoActivo">Activo</label>
        </div>
    </div>

    <div class="col-12">
        <div class="t-ficha-persona">
            <i class="bi bi-info-circle-fill text-primary me-1"></i>
            Los contratos <strong>temporales</strong> necesitan fecha de fin y, al vencer, deben pasar
            <strong>{{ config('proyecto.contratos.meses_entre_temporales') }} meses</strong> antes de volver a contratar a la misma persona con otro temporal.
        </div>
    </div>
</div>

@include('admin.partials.pie-formulario')
