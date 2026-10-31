<form method="POST" action="{{ route('admin.programaciones.reemplazo.guardar') }}" class="t-formulario" autocomplete="off" novalidate
    data-programacion-formulario data-reemplazo
    data-url-validar="{{ route('admin.programaciones.reemplazo.validar') }}">
    @csrf

    <div class="t-alerta-form"></div>

    <div class="t-nota t-nota-ambar mb-3">
        <strong><i class="bi bi-arrow-left-right me-1"></i> ¿Cuándo usarlo?</strong>
        Un conductor o ayudante que falta varios días, que sale de vacaciones o deja la empresa, o un vehículo que va al taller:
        se cambia de una sola vez en <strong>todas las programaciones pendientes</strong> del rango (desde hoy), en lugar de editarlas una por una.
    </div>

    <div class="mb-3">
        <label class="form-label t-obligatorio mb-1">¿Qué desea reemplazar?</label>
        <div class="t-dias-semana" data-error-destino>
            <label class="t-dia"><input type="radio" name="rol" value="conductor" checked><span><i class="bi bi-person-badge me-1"></i>Conductor</span></label>
            <label class="t-dia"><input type="radio" name="rol" value="ayudante"><span><i class="bi bi-person me-1"></i>Ayudante</span></label>
            <label class="t-dia"><input type="radio" name="rol" value="vehiculo"><span><i class="bi bi-truck me-1"></i>Vehículo</span></label>
        </div>
    </div>

    <div class="row g-3 align-items-end mb-3">
        <div class="col-md-4">
            <label for="reemplazoDesde" class="form-label t-obligatorio">Desde</label>
            <input type="date" name="desde" id="reemplazoDesde" class="form-control" required min="{{ today()->toDateString() }}" value="{{ $desde }}">
        </div>
        <div class="col-md-4">
            <label for="reemplazoHasta" class="form-label t-obligatorio">Hasta</label>
            <input type="date" name="hasta" id="reemplazoHasta" class="form-control" required min="{{ today()->toDateString() }}" value="{{ $hasta }}">
        </div>
        <div class="col-md-4">
            <button type="button" class="btn t-boton-contorno w-100" data-validar><i class="bi bi-check2 me-1"></i> Validar disponibilidad</button>
        </div>
    </div>

    <div class="row g-3" data-par="conductor">
        <div class="col-md-6">
            <label for="salienteConductor" class="form-label t-obligatorio">Conductor que sale</label>
            <x-admin.buscador name="saliente_id" id="salienteConductor" :url="route('admin.personal.buscar', ['contexto' => 'conductor'])"
                placeholder="Buscar conductor" mensaje="Seleccione al conductor que sale." vacio="No hay conductores activos." cambiar="Cambiar" />
        </div>
        <div class="col-md-6">
            <label for="entranteConductor" class="form-label t-obligatorio">Conductor que entra</label>
            <x-admin.buscador name="entrante_id" id="entranteConductor" :url="route('admin.personal.buscar', ['contexto' => 'conductor'])"
                placeholder="Buscar conductor" mensaje="Seleccione al conductor que entra." vacio="No hay conductores activos." cambiar="Cambiar" />
        </div>
    </div>

    <div class="row g-3" data-par="ayudante" hidden>
        <div class="col-md-6">
            <label for="salienteAyudante" class="form-label t-obligatorio">Ayudante que sale</label>
            <x-admin.buscador name="saliente_id" id="salienteAyudante" :url="route('admin.personal.buscar', ['contexto' => 'ayudante'])"
                placeholder="Buscar ayudante" mensaje="Seleccione al ayudante que sale." vacio="No hay ayudantes activos." cambiar="Cambiar" />
        </div>
        <div class="col-md-6">
            <label for="entranteAyudante" class="form-label t-obligatorio">Ayudante que entra</label>
            <x-admin.buscador name="entrante_id" id="entranteAyudante" :url="route('admin.personal.buscar', ['contexto' => 'ayudante'])"
                placeholder="Buscar ayudante" mensaje="Seleccione al ayudante que entra." vacio="No hay ayudantes activos." cambiar="Cambiar" />
        </div>
    </div>

    <div class="row g-3" data-par="vehiculo" hidden>
        <div class="col-md-6">
            <label for="salienteVehiculo" class="form-label t-obligatorio">Vehículo que sale</label>
            <x-admin.buscador name="saliente_id" id="salienteVehiculo" :url="route('admin.vehiculos.buscar')"
                placeholder="Buscar por nombre o placa" mensaje="Seleccione el vehículo que sale." vacio="No hay vehículos activos." cambiar="Cambiar" />
        </div>
        <div class="col-md-6">
            <label for="entranteVehiculo" class="form-label t-obligatorio">Vehículo que entra</label>
            <x-admin.buscador name="entrante_id" id="entranteVehiculo" :url="route('admin.vehiculos.buscar')"
                placeholder="Buscar por nombre o placa" mensaje="Seleccione el vehículo que entra." vacio="No hay vehículos activos." cambiar="Cambiar" />
        </div>
    </div>

    <div class="mt-3">
        @include('admin.cambios._motivo', ['motivos' => $motivos])
    </div>

    <div class="mt-3" data-resultado></div>

    <div class="t-pie-form">
        <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
        <button type="submit" class="btn t-boton" data-guardar disabled><i class="bi bi-arrow-left-right me-1"></i> Aplicar reemplazo</button>
    </div>
</form>
