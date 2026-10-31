@php
    $datosGrupos = $grupos->map(fn ($g) => [
        'id' => $g->id, 'nombre' => $g->name, 'zona' => $g->zona?->name, 'turno_id' => $g->shift_id, 'turno' => $g->turno?->name,
        'vehiculo' => $g->vehiculo ? ['codigo' => $g->vehiculo->code, 'placa' => $g->vehiculo->plate, 'capacidad' => (int) $g->vehiculo->occupant_capacity] : null,
        'dias' => $g->dias_ordenados, 'conductor' => $g->driver_id, 'ayudantes' => $g->ayudantes->pluck('id')->values()->all(),
    ])->values();
@endphp

<form method="POST" action="{{ route('admin.programaciones.masiva.guardar') }}" class="t-formulario" autocomplete="off" novalidate
    data-programacion-formulario data-masiva
    data-grupos="{{ json_encode($datosGrupos) }}"
    data-conductores="{{ json_encode($conductores) }}"
    data-ayudantes="{{ json_encode($ayudantes) }}"
    data-url-validar="{{ route('admin.programaciones.masiva.validar') }}"
    data-url-feriados="{{ route('admin.programaciones.masiva.feriados') }}"
    data-url-grupo="{{ url('admin/programaciones/grupo') }}">
    @csrf

    <div class="t-alerta-form"></div>

    <div class="t-nota t-nota-ambar mb-3">
        <strong><i class="bi bi-info-circle-fill me-1"></i> Programación masiva:</strong>
        elija el rango de fechas; cada grupo se programa como está definido y, si hace falta, cambie aquí a su conductor o ayudantes solo para esta programación.
        Al validar se revisa grupo por grupo.
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-4">
            <label for="masivaInicio" class="form-label t-obligatorio">Fecha de inicio</label>
            <input type="date" name="inicio" id="masivaInicio" class="form-control" required min="{{ today()->toDateString() }}">
        </div>
        <div class="col-sm-6 col-lg-4">
            <label for="masivaFin" class="form-label t-obligatorio">Fecha de fin</label>
            <input type="date" name="fin" id="masivaFin" class="form-control" required min="{{ today()->toDateString() }}">
        </div>
        <div class="col-lg-4">
            <button type="button" class="btn t-boton-contorno w-100" data-validar><i class="bi bi-check2 me-1"></i> Validar disponibilidad</button>
        </div>
    </div>

    <div class="mt-3">
        <div class="small fw-semibold text-muted mb-1">Mostrar los grupos del turno:</div>
        <div class="t-atajos" data-filtro-turnos>
            <button type="button" class="t-atajo" data-turno="" aria-pressed="true">Todos los turnos</button>
            @foreach ($turnos as $turno)
                <button type="button" class="t-atajo" data-turno="{{ $turno->id }}" aria-pressed="false">{{ $turno->name }}</button>
            @endforeach
        </div>
    </div>

    <div class="t-nota mt-3">
        <div class="fw-semibold mb-1"><i class="bi bi-flag-fill text-danger me-1"></i> Feriados en el rango</div>
        <div class="small text-muted mb-2">Los marcados <strong>no se programan</strong>, aunque el grupo trabaje ese día. Desmarque los que sí quiera trabajar.</div>
        <div class="small" data-feriados-caja>Elija las fechas para ver los feriados.</div>
    </div>

    <div class="mt-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h6 class="t-seccion-titulo mb-0"><i class="bi bi-people-fill"></i> Grupos de trabajo <span class="text-muted fw-normal">(<span data-contador-grupos>0</span> de {{ $grupos->count() }} incluidos)</span></h6>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-3" data-restaurar hidden><i class="bi bi-arrow-counterclockwise me-1"></i> Volver a incluir los grupos quitados</button>
        </div>

        @if ($grupos->isEmpty())
            <div class="t-vacio">
                <i class="bi bi-people"></i>
                <h6>No hay grupos de personal activos</h6>
                <p>Cree sus grupos en «Grupos de personal» para poder programar de forma masiva.</p>
            </div>
        @else
            <div class="av-grupos" data-grupos-grid data-error-destino></div>
        @endif
    </div>

    <div data-resultado class="mt-3"></div>

    <div class="t-pie-form">
        <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
        <button type="submit" class="btn t-boton" data-guardar disabled><i class="bi bi-calendar2-check-fill me-1"></i> Generar programaciones</button>
    </div>
</form>
