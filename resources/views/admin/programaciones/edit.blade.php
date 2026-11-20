@php
    $colorEstado = ['finalizado' => 'verde', 'reprogramado' => 'ambar'];
    $hora = fn ($t) => substr((string) $t?->time_start, 0, 8).' - '.substr((string) $t?->time_end, 0, 8);
    $personas = collect([['id' => $programacion->driver_id, 'nombre' => $programacion->conductor?->nombre_completo, 'rol' => 'conductor', 'campo' => 'conductor']])
        ->merge($programacion->ayudantes->values()->map(fn ($a, $i) => ['id' => $a->id, 'nombre' => $a->nombre_completo, 'rol' => 'ayudante', 'campo' => 'ayudante_'.($i + 1)]));
    $estado = [
        'turno' => $programacion->shift_id, 'vehiculo' => $programacion->vehicle_id, 'conductor' => $programacion->driver_id,
        'ayudantes' => $programacion->ayudantes->pluck('id')->values()->all(),
    ];
    $primeraFalta = $personas->first(fn ($p) => ($estadoPersonal[$p['id']]['estado'] ?? 'ok') === 'faltante');
@endphp

<form method="POST" action="{{ route('admin.programaciones.update', $programacion->id) }}" class="t-formulario" autocomplete="off" novalidate
    data-programacion-formulario data-modificar
    data-estado="{{ json_encode($estado) }}"
    data-notas="{{ $programacion->notes }}"
    data-primera-falta="{{ $primeraFalta['campo'] ?? '' }}"
    data-url-editar="{{ route('admin.programaciones.edit', $programacion->id) }}"
    data-url-cambios="{{ url('admin/cambios') }}"
    data-url-candidatos="{{ route('admin.programaciones.candidatos', $programacion->id) }}"
    data-url-validar="{{ route('admin.programaciones.validar.cambio', $programacion->id) }}">
    @csrf
    @method('PUT')

    <div class="t-alerta-form"></div>

    <div class="t-resumen-grupo">
        <div><small>Fecha</small><strong>{{ $programacion->date->format('d/m/Y') }}</strong></div>
        <div><small>Zona</small><strong>{{ $programacion->zona?->name }}</strong></div>
        <div><small>Grupo</small><strong>{{ $programacion->grupo?->name ?? '—' }}</strong></div>
        <div><small>Estado</small><strong><span class="t-insignia t-{{ $colorEstado[$programacion->status] ?? 'azul' }}">{{ \App\Models\Programacion::ESTADOS[$programacion->status] }}</span></strong></div>
    </div>

    <div class="row g-3">
        {{-- Cambio de turno --}}
        <div class="col-lg-4">
            <section class="av-panel" data-panel="turno">
                <h6 class="av-panel-titulo"><span class="turno"><i class="bi bi-clock-fill"></i></span> Cambio de turno</h6>
                <div>
                    <label for="turnoActual">Turno actual</label>
                    <input type="text" id="turnoActual" class="form-control av-solo-lectura" readonly value="{{ $programacion->turno?->name }} ({{ $hora($programacion->turno) }})">

                    <label for="turnoNuevo">Nuevo turno</label>
                    <select id="turnoNuevo" class="form-select" data-nuevo>
                        <option value="">Seleccione un nuevo turno</option>
                        @foreach ($turnos as $turno)
                            @continue($turno->id === $programacion->shift_id)
                            <option value="{{ $turno->id }}" data-texto="{{ $turno->name }} ({{ $hora($turno) }})">{{ $turno->name }} ({{ $hora($turno) }})</option>
                        @endforeach
                    </select>

                    @include('admin.cambios._motivo', ['motivos' => $motivos, 'campo' => 'turno', 'etiqueta' => 'de turno'])
                    <button type="button" class="btn t-boton-contorno btn-sm mt-3" data-agregar disabled><i class="bi bi-plus-lg me-1"></i> Agregar cambio</button>
                    <div class="av-aviso t-validacion" data-aviso hidden></div>
                </div>
            </section>
        </div>

        {{-- Cambio de vehículo --}}
        <div class="col-lg-4">
            <section class="av-panel" data-panel="vehiculo">
                <h6 class="av-panel-titulo"><span class="vehiculo"><i class="bi bi-truck"></i></span> Cambio de vehículo</h6>
                <div>
                    <label for="vehiculoActual">Vehículo actual</label>
                    <input type="text" id="vehiculoActual" class="form-control av-solo-lectura" readonly value="{{ $programacion->vehiculo?->name }} - {{ $programacion->vehiculo?->plate }}">

                    <label for="vehiculoNuevo">Nuevo vehículo</label>
                    <select id="vehiculoNuevo" class="form-select" data-nuevo>
                        <option value="">Seleccione un nuevo vehículo</option>
                        @foreach ($vehiculos as $vehiculo)
                            @continue($vehiculo->id === $programacion->vehicle_id)
                            <option value="{{ $vehiculo->id }}" data-texto="{{ $vehiculo->name }} - {{ $vehiculo->plate }}">{{ $vehiculo->name }} - {{ $vehiculo->plate }} (capacidad {{ $vehiculo->occupant_capacity }})</option>
                        @endforeach
                    </select>

                    @include('admin.cambios._motivo', ['motivos' => $motivos, 'campo' => 'vehiculo', 'etiqueta' => 'de vehículo'])
                    <button type="button" class="btn t-boton-contorno btn-sm mt-3" data-agregar disabled><i class="bi bi-plus-lg me-1"></i> Agregar cambio</button>
                    <div class="av-aviso t-validacion" data-aviso hidden></div>
                </div>
            </section>
        </div>

        {{-- Cambio de personal --}}
        <div class="col-lg-4">
            <section class="av-panel" data-panel="personal">
                <h6 class="av-panel-titulo"><span class="personal"><i class="bi bi-people-fill"></i></span> Cambio de personal</h6>
                <div>
                    <label for="personalActual">Personal actual</label>
                    <select id="personalActual" class="form-select" data-actual>
                        <option value="">Seleccione un personal</option>
                        @foreach ($personas as $persona)
                            @php($falta = $estadoPersonal[$persona['id']] ?? null)
                            <option value="{{ $persona['id'] }}" data-campo="{{ $persona['campo'] }}" data-rol="{{ $persona['rol'] }}" data-texto="{{ $persona['nombre'] }}">
                                {{ ($falta['estado'] ?? 'ok') === 'faltante' ? '⚠ ' : '' }}{{ $persona['nombre'] }} ({{ $persona['rol'] }}){{ ($falta['estado'] ?? 'ok') === 'faltante' ? ' — '.$falta['motivo'] : '' }}
                            </option>
                        @endforeach
                    </select>

                    <label for="personalNuevo">Nuevo personal</label>
                    <select id="personalNuevo" class="form-select" data-nuevo disabled>
                        <option value="">Buscar empleado disponible...</option>
                    </select>

                    @include('admin.cambios._motivo', ['motivos' => $motivos, 'campo' => 'personal', 'etiqueta' => 'de personal'])
                    <button type="button" class="btn t-boton-contorno btn-sm mt-3" data-agregar disabled><i class="bi bi-plus-lg me-1"></i> Agregar cambio</button>
                    <div class="av-aviso t-validacion" data-aviso hidden></div>
                </div>
            </section>
        </div>
    </div>

    {{-- Cambios que se aplicarán al guardar --}}
    <div class="av-tabla-seccion">
        <h6 class="t-seccion-titulo"><i class="bi bi-list-ul"></i> Cambios registrados</h6>
        <div class="table-responsive">
            <table class="table t-tabla mb-0">
                <thead>
                    <tr>
                        <th>Tipo de cambio</th>
                        <th>Valor anterior</th>
                        <th>Valor nuevo</th>
                        <th>Motivo</th>
                        <th>Descripción</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody data-cambios-nuevos></tbody>
            </table>
        </div>
        <div class="av-vacio-tabla" data-sin-cambios><i class="bi bi-info-circle-fill me-1"></i> No hay cambios registrados. Agregue cambios usando los botones de arriba.</div>
    </div>

    <div class="mt-3">
        <label for="cambioNotas" class="form-label fw-semibold small">Observaciones</label>
        <textarea name="notas" id="cambioNotas" class="form-control" rows="2" maxlength="500" placeholder="Observaciones adicionales...">{{ old('notas', $programacion->notes) }}</textarea>
    </div>

    <div data-campos-ocultos></div>

    <div class="t-pie-form">
        <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
        <button type="submit" class="btn t-boton" data-guardar disabled><i class="bi bi-floppy-fill me-1"></i> Guardar cambios</button>
    </div>
</form>

<div class="av-tabla-seccion">
    <h6 class="t-seccion-titulo"><i class="bi bi-clock-history"></i> Historial de cambios de esta programación</h6>
    @if ($cambios->isEmpty())
        <div class="av-vacio-tabla">Esta programación todavía no tiene cambios aplicados.</div>
    @else
        <div class="table-responsive">
            <table class="table t-tabla mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Valor anterior</th>
                        <th>Valor nuevo</th>
                        <th>Motivo</th>
                        <th>Usuario</th>
                        <th class="text-center">Deshacer</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cambios as $cambio)
                        <tr>
                            <td><span class="t-fecha">{{ $cambio->created_at?->format('d/m/Y') }}<small>{{ $cambio->created_at?->format('H:i') }}</small></span></td>
                            <td><span class="t-insignia t-ambar">{{ $cambio->tipo_texto }}</span></td>
                            <td>{{ $cambio->old_label }}</td>
                            <td><strong>{{ $cambio->new_label }}</strong></td>
                            <td>{{ $cambio->reason_name ?? 'Motivo propio' }}@if ($cambio->description)<small class="d-block text-muted">{{ $cambio->description }}</small>@endif</td>
                            <td>{{ $cambio->usuario?->name ?? 'Sistema' }}</td>
                            <td class="text-center">
                                <button type="button" class="btn t-boton-icono t-eliminar" data-deshacer-cambio="{{ $cambio->batch_id }}" data-cambio="{{ $cambio->id }}"
                                    data-texto="{{ $cambio->tipo_texto }}: {{ $cambio->old_label }} → {{ $cambio->new_label }}" title="Deshacer este cambio" data-bs-toggle="tooltip"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
