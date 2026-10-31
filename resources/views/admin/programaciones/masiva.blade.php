@php
    $nombresDias = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
@endphp

<form method="POST" action="{{ route('admin.programaciones.masiva.guardar') }}" class="t-formulario" autocomplete="off" novalidate
    data-programacion-formulario data-masiva
    data-url-validar="{{ route('admin.programaciones.masiva.validar') }}">
    @csrf

    <div class="t-alerta-form"></div>

    <div class="t-nota t-nota-ambar mb-3">
        <strong><i class="bi bi-info-circle-fill me-1"></i> Programación masiva:</strong>
        cada grupo elegido se programa tal como está definido (turno, zona, vehículo, días, conductor y ayudantes) en el rango de fechas indicado.
        Si necesita cambiar a alguien, use «Nueva programación» o edite el grupo.
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="masivaInicio" class="form-label t-obligatorio">Fecha de inicio</label>
            <input type="date" name="inicio" id="masivaInicio" class="form-control" required min="{{ today()->toDateString() }}">
        </div>
        <div class="col-md-4">
            <label for="masivaFin" class="form-label t-obligatorio">Fecha de fin</label>
            <input type="date" name="fin" id="masivaFin" class="form-control" required min="{{ today()->toDateString() }}">
        </div>
        <div class="col-md-4">
            <button type="button" class="btn t-boton-contorno w-100" data-validar><i class="bi bi-check2 me-1"></i> Validar disponibilidad</button>
        </div>

        <div class="col-12" data-resultado></div>

        <div class="col-12">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                <label class="form-label t-obligatorio mb-0">Grupos de personal <span class="text-muted fw-normal">(<span data-contador-grupos>0</span> de {{ $grupos->count() }} elegidos)</span></label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="search" class="form-control form-control-sm" style="width: 14rem" placeholder="Filtrar grupos..." data-filtro-grupos aria-label="Filtrar grupos">
                    <label class="t-dia mb-0"><input type="checkbox" data-todos-grupos><span>Todos</span></label>
                </div>
            </div>

            @if ($grupos->isEmpty())
                <div class="t-vacio">
                    <i class="bi bi-people"></i>
                    <h6>No hay grupos de personal activos</h6>
                    <p>Cree sus grupos en «Grupos de personal» para poder programar de forma masiva.</p>
                </div>
            @else
                <div class="t-lista-grupos" data-error-destino>
                    @foreach ($grupos as $grupo)
                        <label class="t-grupo-opcion" data-grupo-opcion data-texto="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($grupo->name.' '.$grupo->zona?->name.' '.$grupo->turno?->name.' '.$grupo->vehiculo?->plate.' '.$grupo->conductor?->nombre_completo)) }}">
                            <input type="checkbox" name="grupos[]" value="{{ $grupo->id }}">
                            <span class="t-grupo-nombre"><strong>{{ $grupo->name }}</strong><small>{{ $grupo->zona?->name }} · {{ $grupo->turno?->name }}</small></span>
                            <span class="t-grupo-dato"><small>Vehículo</small>{{ $grupo->vehiculo?->plate }}</span>
                            <span class="t-grupo-dato"><small>Conductor</small>{{ $grupo->conductor?->nombre_completo }}</span>
                            <span class="t-grupo-dias">
                                @foreach ($nombresDias as $numero => $abreviatura)
                                    <i @class(['activo' => in_array($numero, $grupo->dias_ordenados, true)])>{{ $abreviatura }}</i>
                                @endforeach
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="t-pie-form">
        <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
        <button type="submit" class="btn t-boton" data-guardar disabled><i class="bi bi-calendar2-check-fill me-1"></i> Generar programaciones</button>
    </div>
</form>
