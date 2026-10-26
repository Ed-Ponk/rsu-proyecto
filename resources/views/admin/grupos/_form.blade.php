@php
    $editando = isset($grupo);
    $diasElegidos = collect(old('weekdays', $editando ? $grupo->dias_ordenados : []))->map(fn ($d) => (int) $d)->all();
@endphp

<div class="t-alerta-form"></div>

<div class="row g-3" data-grupo-formulario>
    <div class="col-md-6">
        <label for="grupoNombre" class="form-label t-obligatorio">Nombre del grupo</label>
        <input type="text" name="name" id="grupoNombre" class="form-control" maxlength="100" required
            placeholder="Ejemplo: Grupo A" value="{{ old('name', $grupo->name ?? '') }}">
    </div>

    <div class="col-md-6">
        <label for="grupoTurno" class="form-label t-obligatorio">Turno</label>
        <x-admin.buscador name="shift_id" id="grupoTurno" :url="route('admin.turnos.buscar')" :inicial="$iniciales['turno']"
            placeholder="Buscar turno" mensaje="Seleccione un turno de la lista." vacio="No hay turnos registrados." cambiar="Cambiar turno" />
    </div>

    <div class="col-md-6">
        <label for="grupoZona" class="form-label t-obligatorio">Zona</label>
        <x-admin.buscador name="zone_id" id="grupoZona" :url="route('admin.zonas.buscar')" :inicial="$iniciales['zona']"
            placeholder="Buscar zona" mensaje="Seleccione una zona de la lista." vacio="No hay zonas activas registradas." cambiar="Cambiar zona" />
    </div>

    <div class="col-md-6">
        <label for="grupoVehiculo" class="form-label t-obligatorio">Vehículo</label>
        <x-admin.buscador name="vehicle_id" id="grupoVehiculo" :url="route('admin.vehiculos.buscar')" :inicial="$iniciales['vehiculo']"
            placeholder="Buscar por nombre o placa" mensaje="Seleccione un vehículo de la lista." vacio="No hay vehículos activos registrados."
            cambiar="Cambiar vehículo" data-vehiculo />
        <div class="form-text" data-capacidad-texto>Elija un vehículo para saber cuántos ayudantes caben.</div>
    </div>

    <div class="col-12">
        <label class="form-label t-obligatorio mb-1">Días de trabajo</label>
        <div class="t-dias-semana" data-error-destino>
            @foreach ($dias as $numero => $nombre)
                <label class="t-dia">
                    <input type="checkbox" name="weekdays[]" value="{{ $numero }}" @checked(in_array($numero, $diasElegidos, true))>
                    <span>{{ $nombre }}</span>
                </label>
            @endforeach
        </div>
        <div class="form-text">
            Atajos:
            <a href="#" data-dias="1,2,3,4,5">Lunes a viernes</a> ·
            <a href="#" data-dias="1,2,3,4,5,6">Lunes a sábado</a> ·
            <a href="#" data-dias="1,2,3,4,5,6,7">Todos</a> ·
            <a href="#" data-dias="">Ninguno</a>
        </div>
    </div>

    <div class="col-12"><hr class="my-1"></div>

    <div class="col-md-6">
        <label for="grupoConductor" class="form-label t-obligatorio">Conductor</label>
        <x-admin.buscador name="driver_id" id="grupoConductor" :url="route('admin.personal.buscar', ['contexto' => 'conductor'])" :inicial="$iniciales['conductor']"
            placeholder="Buscar conductor por nombre o DNI" mensaje="Seleccione un conductor de la lista." vacio="No hay conductores activos."
            cambiar="Cambiar conductor" />
    </div>

    <div class="col-12">
        <div class="row g-3" data-ranuras-ayudantes
            data-url="{{ route('admin.personal.buscar', ['contexto' => 'ayudante']) }}"
            data-iniciales="{{ json_encode($iniciales['ayudantes']) }}"></div>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="status" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="status" id="grupoActivo" value="1"
                @checked(old('status', $grupo->status ?? true))>
            <label class="form-check-label fw-semibold" for="grupoActivo">Grupo activo (se puede usar para programar)</label>
        </div>
    </div>
</div>

@include('admin.partials.pie-formulario')
