<form method="POST" action="{{ route('admin.programaciones.update', $programacion->id) }}" class="t-formulario" autocomplete="off" novalidate
    data-programacion-formulario
    data-url-validar="{{ route('admin.programaciones.validar.cambio', $programacion->id) }}">
    @csrf
    @method('PUT')

    <div class="t-alerta-form"></div>

    <div class="t-resumen-grupo">
        <div><small>Fecha</small><strong>{{ $programacion->date->format('d/m/Y') }}</strong></div>
        <div><small>Zona</small><strong>{{ $programacion->zona?->name }}</strong></div>
        <div><small>Turno</small><strong>{{ $programacion->turno?->name }}
            ({{ substr($programacion->turno?->time_start, 0, 5) }} - {{ substr($programacion->turno?->time_end, 0, 5) }})</strong></div>
        <div><small>Estado</small><strong>{{ \App\Models\Programacion::ESTADOS[$programacion->status] }}</strong></div>
    </div>

    <div class="row g-3">
        <div class="col-12" data-resultado></div>

        <div class="col-md-6">
            <label for="cambioVehiculo" class="form-label t-obligatorio">Vehículo</label>
            <x-admin.buscador name="vehiculo_id" id="cambioVehiculo" :url="route('admin.vehiculos.buscar')" :inicial="$vehiculoItem"
                placeholder="Buscar por nombre o placa" mensaje="Seleccione un vehículo de la lista." vacio="No hay vehículos activos."
                cambiar="Cambiar vehículo" data-vehiculo />
        </div>

        <div class="col-md-6">
            <label for="cambioConductor" class="form-label t-obligatorio">Conductor</label>
            <x-admin.buscador name="conductor_id" id="cambioConductor" :url="route('admin.personal.buscar', ['contexto' => 'conductor'])" :inicial="$conductorItem"
                placeholder="Buscar conductor por nombre o DNI" mensaje="Seleccione un conductor de la lista."
                vacio="No hay conductores activos." cambiar="Cambiar conductor" data-conductor />
        </div>

        <div class="col-12">
            <div class="row g-3" data-ranuras-ayudantes
                data-url="{{ route('admin.personal.buscar', ['contexto' => 'ayudante']) }}"
                data-iniciales="{{ json_encode($ayudantesItems) }}"></div>
        </div>

        <div class="col-12">
            <label for="cambioNotas" class="form-label">Observaciones</label>
            <textarea name="notas" id="cambioNotas" class="form-control" rows="2" maxlength="500"
                placeholder="Observaciones adicionales...">{{ old('notas', $programacion->notes) }}</textarea>
        </div>

        <div class="col-12">
            <button type="button" class="btn t-boton-contorno" data-validar><i class="bi bi-check2 me-1"></i> Validar disponibilidad</button>
        </div>
    </div>

    <div class="t-pie-form">
        <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
        <button type="submit" class="btn t-boton" data-guardar disabled><i class="bi bi-floppy-fill me-1"></i> Guardar cambios</button>
    </div>
</form>
