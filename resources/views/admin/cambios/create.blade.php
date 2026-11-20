<form method="POST" action="{{ route('admin.cambios.store') }}" class="t-formulario" autocomplete="off" novalidate
    data-programacion-formulario data-cambio-masivo
    data-url-validar="{{ route('admin.cambios.validar') }}">
    @csrf

    <div class="t-alerta-form"></div>

    <div class="row g-3">
        <div class="col-sm-6 col-lg-3">
            <label for="cambioDesde" class="form-label fw-semibold small t-obligatorio">Fecha de inicio</label>
            <input type="date" name="desde" id="cambioDesde" class="form-control" required min="{{ today()->toDateString() }}" value="{{ $desde }}">
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="cambioHasta" class="form-label fw-semibold small t-obligatorio">Fecha de fin</label>
            <input type="date" name="hasta" id="cambioHasta" class="form-control" required min="{{ today()->toDateString() }}" value="{{ $hasta }}">
        </div>
        <div class="col-lg-3">
            <label for="cambioZonaElegir" class="form-label fw-semibold small">Zonas <span class="text-muted fw-normal">(opcional)</span></label>
            <select id="cambioZonaElegir" class="form-select" data-zona-elegir>
                <option value="">Agregar una zona...</option>
                @foreach ($zonas as $zona)
                    <option value="{{ $zona->id }}">{{ $zona->name }}</option>
                @endforeach
            </select>
            <div class="d-flex flex-wrap gap-1 mt-1" data-zonas-elegidas></div>
            <div class="form-text">Dejar vacío para aplicar a todas las zonas.</div>
        </div>
        <div class="col-lg-3">
            <label for="cambioTipo" class="form-label fw-semibold small t-obligatorio">Tipo de cambio</label>
            <select name="rol" id="cambioTipo" class="form-select" required>
                <option value="turno">Cambio de turno</option>
                <option value="vehiculo">Cambio de vehículo</option>
                <option value="conductor">Cambio de conductor</option>
                <option value="ayudante">Cambio de ocupante</option>
            </select>
        </div>
    </div>

    <div class="row g-3 mt-0" data-par="turno">
        <div class="col-md-6">
            <label for="salienteTurno" class="form-label fw-semibold small t-obligatorio">Turno a reemplazar</label>
            <select name="saliente_id" id="salienteTurno" class="form-select" required>
                <option value="">Seleccione el turno actual</option>
                @foreach ($turnos as $turno)
                    <option value="{{ $turno->id }}">{{ $turno->name }} ({{ substr($turno->time_start, 0, 8) }} - {{ substr($turno->time_end, 0, 8) }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label for="entranteTurno" class="form-label fw-semibold small t-obligatorio">Nuevo turno</label>
            <select name="entrante_id" id="entranteTurno" class="form-select" required>
                <option value="">Seleccione el turno nuevo</option>
                @foreach ($turnos as $turno)
                    <option value="{{ $turno->id }}">{{ $turno->name }} ({{ substr($turno->time_start, 0, 8) }} - {{ substr($turno->time_end, 0, 8) }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-3 mt-0" data-par="vehiculo" hidden>
        <div class="col-md-6">
            <label for="salienteVehiculo" class="form-label fw-semibold small t-obligatorio">Vehículo a reemplazar</label>
            <x-admin.buscador name="saliente_id" id="salienteVehiculo" :url="route('admin.vehiculos.buscar')"
                placeholder="Buscar por nombre o placa" mensaje="Seleccione el vehículo que se cambia." vacio="No hay vehículos activos." cambiar="Cambiar" />
        </div>
        <div class="col-md-6">
            <label for="entranteVehiculo" class="form-label fw-semibold small t-obligatorio">Nuevo vehículo</label>
            <x-admin.buscador name="entrante_id" id="entranteVehiculo" :url="route('admin.vehiculos.buscar')"
                placeholder="Buscar por nombre o placa" mensaje="Seleccione el vehículo nuevo." vacio="No hay vehículos activos." cambiar="Cambiar" />
        </div>
    </div>

    <div class="row g-3 mt-0" data-par="conductor" hidden>
        <div class="col-md-6">
            <label for="salienteConductor" class="form-label fw-semibold small t-obligatorio">Conductor a reemplazar</label>
            <x-admin.buscador name="saliente_id" id="salienteConductor" :url="route('admin.personal.buscar', ['contexto' => 'conductor'])"
                placeholder="Buscar conductor" mensaje="Seleccione el conductor que sale." vacio="No hay conductores activos." cambiar="Cambiar" />
        </div>
        <div class="col-md-6">
            <label for="entranteConductor" class="form-label fw-semibold small t-obligatorio">Nuevo conductor</label>
            <x-admin.buscador name="entrante_id" id="entranteConductor" :url="route('admin.personal.buscar', ['contexto' => 'conductor'])"
                placeholder="Buscar conductor" mensaje="Seleccione el conductor que entra." vacio="No hay conductores activos." cambiar="Cambiar" />
        </div>
    </div>

    <div class="row g-3 mt-0" data-par="ayudante" hidden>
        <div class="col-md-6">
            <label for="salienteAyudante" class="form-label fw-semibold small t-obligatorio">Ocupante a reemplazar</label>
            <x-admin.buscador name="saliente_id" id="salienteAyudante" :url="route('admin.personal.buscar', ['contexto' => 'ayudante'])"
                placeholder="Buscar ayudante" mensaje="Seleccione el ayudante que sale." vacio="No hay ayudantes activos." cambiar="Cambiar" />
        </div>
        <div class="col-md-6">
            <label for="entranteAyudante" class="form-label fw-semibold small t-obligatorio">Nuevo ocupante</label>
            <x-admin.buscador name="entrante_id" id="entranteAyudante" :url="route('admin.personal.buscar', ['contexto' => 'ayudante'])"
                placeholder="Buscar ayudante" mensaje="Seleccione el ayudante que entra." vacio="No hay ayudantes activos." cambiar="Cambiar" />
        </div>
    </div>

    <div class="row g-3 mt-0" data-motivo>
        <div class="col-md-6">
            <label for="motivo_masivo" class="form-label fw-semibold small t-obligatorio">Motivo predefinido</label>
            <select name="motivo_id" id="motivo_masivo" class="form-select" data-motivo-select>
                <option value="">Seleccione un motivo</option>
                @foreach ($motivos as $motivo)
                    <option value="{{ $motivo->id }}" data-nombre="{{ $motivo->name }}" data-descripcion="{{ $motivo->description }}">{{ $motivo->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Seleccione un motivo predefinido para el cambio.</div>
        </div>
        <div class="col-md-6">
            <label for="motivoAdicional" class="form-label fw-semibold small">Descripción adicional <span class="text-muted fw-normal">(opcional)</span></label>
            <input type="text" id="motivoAdicional" class="form-control" maxlength="300" placeholder="Ej.: obras en la municipalidad" data-motivo-adicional>
            <div class="form-text">Complemento al motivo predefinido.</div>
        </div>
        <div class="col-12">
            <label for="detalle_masivo" class="form-label fw-semibold small t-obligatorio">Descripción completa del cambio</label>
            <textarea name="descripcion" id="detalle_masivo" class="form-control" rows="2" maxlength="500" placeholder="Se completa solo con el motivo y la descripción adicional" data-motivo-detalle></textarea>
        </div>
    </div>

    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn t-boton-contorno" data-validar><i class="bi bi-check-circle-fill me-1"></i> Validar disponibilidad</button>
        <span class="small text-muted">También se valida solo al guardar.</span>
    </div>
    <div class="mt-3" data-resultado></div>

    <div class="t-pie-form">
        <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
        <button type="submit" class="btn t-boton" data-guardar><i class="bi bi-floppy-fill me-1"></i> Guardar</button>
    </div>
</form>
