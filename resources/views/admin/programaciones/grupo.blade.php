@php
    $colorEstado = ['finalizado' => 'verde', 'reprogramado' => 'ambar'];
    $colorTipo = ['turno' => 'violeta', 'vehiculo' => 'azul', 'conductor' => 'verde', 'ayudante' => 'turquesa'];
@endphp

<div class="t-resumen-grupo">
    <div><small>Grupo</small><strong>{{ $grupo->name }}</strong></div>
    <div><small>Zona</small><strong>{{ $grupo->zona?->name }}</strong></div>
    <div><small>Turno</small><strong>{{ $grupo->turno?->name }}</strong></div>
    <div><small>Vehículo</small><strong>{{ $grupo->vehiculo?->name }} <span class="t-codigo">{{ $grupo->vehiculo?->plate }}</span></strong></div>
</div>

<div class="mb-3">
    <small class="text-muted me-1">Programaciones del grupo:</small>
    @forelse ($programaciones as $estado => $total)
        <span class="t-insignia t-{{ $colorEstado[$estado] ?? 'azul' }}">{{ \App\Models\Programacion::ESTADOS[$estado] ?? $estado }}: {{ $total }}</span>
    @empty
        <span class="text-muted">Todavía no tiene programaciones.</span>
    @endforelse
</div>

<div class="av-tabla-seccion mt-0">
    <h6 class="t-seccion-titulo"><i class="bi bi-people-fill"></i> Personal asignado</h6>
    <div class="table-responsive">
        <table class="table t-tabla mb-0">
            <thead><tr><th>Rol</th><th>Nombre</th></tr></thead>
            <tbody>
                <tr>
                    <td><span class="t-insignia t-azul">Conductor</span></td>
                    <td><span class="av-persona"><span class="t-avatar">{{ mb_strtoupper(mb_substr($grupo->conductor?->name ?? '?', 0, 1)) }}</span><strong>{{ $grupo->conductor?->nombre_completo ?? '—' }}</strong></span></td>
                </tr>
                @foreach ($grupo->ayudantes as $ayudante)
                    <tr>
                        <td><span class="t-insignia t-verde">Ayudante</span></td>
                        <td><span class="av-persona"><span class="t-avatar">{{ mb_strtoupper(mb_substr($ayudante->name, 0, 1)) }}</span><strong>{{ $ayudante->nombre_completo }}</strong></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="av-tabla-seccion">
    <h6 class="t-seccion-titulo"><i class="bi bi-clock-history"></i> Historial de cambios <small class="ms-auto fw-normal text-muted">individuales y masivos</small></h6>
    @if ($cambios->isEmpty())
        <div class="av-vacio-tabla"><i class="bi bi-arrow-left-right me-1"></i> Este grupo no tiene cambios registrados.</div>
    @else
        <div class="table-responsive">
            <table class="table t-tabla mb-0">
                <thead>
                    <tr><th>Fecha</th><th>Tipo</th><th>Anterior</th><th>Nuevo</th><th>Motivo</th></tr>
                </thead>
                <tbody>
                    @foreach ($cambios as $cambio)
                        @php($antes = $cambio->valor('old'))
                        @php($despues = $cambio->valor('new'))
                        <tr>
                            <td><span class="t-fecha">{{ $cambio->created_at?->format('d/m/Y') }}<small>{{ $cambio->created_at?->format('H:i') }}</small></span></td>
                            <td>
                                <span class="t-insignia t-{{ $colorTipo[$cambio->type] ?? 'gris' }}">{{ $cambio->tipo_texto }}</span>
                                <small class="d-block text-muted mt-1">{{ $cambio->scope === 'masivo' ? 'Masivo' : 'Individual' }} · prog. {{ $cambio->programacion?->date->format('d/m/Y') }}</small>
                            </td>
                            <td><div class="av-valor"><i class="bi bi-circle-fill av-antes"></i><div>{{ $antes['titulo'] }}<small>{{ $antes['detalle'] }}</small></div></div></td>
                            <td><div class="av-valor"><i class="bi bi-circle-fill av-despues"></i><div><strong>{{ $despues['titulo'] }}</strong><small>{{ $despues['detalle'] }}</small></div></div></td>
                            <td>{{ $cambio->reason_name ?? 'Motivo propio' }}@if ($cambio->description)<small class="d-block text-muted">{{ $cambio->description }}</small>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="t-pie-form">
    <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button>
</div>
