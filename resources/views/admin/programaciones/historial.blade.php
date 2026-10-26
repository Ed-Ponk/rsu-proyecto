@php
    $colorAccion = ['creado' => 'verde', 'modificado' => 'ambar', 'eliminado' => 'rojo'];
@endphp

<div class="t-resumen-grupo">
    <div><small>Fecha</small><strong>{{ $programacion->date->format('d/m/Y') }}</strong></div>
    <div><small>Estado</small><strong>{{ \App\Models\Programacion::ESTADOS[$programacion->status] ?? $programacion->status }}</strong></div>
    <div><small>Grupo</small><strong>{{ $programacion->grupo?->name ?? '—' }}</strong></div>
    <div><small>Zona</small><strong>{{ $programacion->zona?->name }}</strong></div>
    <div><small>Turno</small><strong>{{ $programacion->turno?->name }}
        ({{ substr($programacion->turno?->time_start, 0, 5) }} - {{ substr($programacion->turno?->time_end, 0, 5) }})</strong></div>
    <div><small>Vehículo</small><strong>{{ $programacion->vehiculo?->name }} · {{ $programacion->vehiculo?->plate }}</strong></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <small class="text-muted d-block">Conductor</small>
        <strong>{{ $programacion->conductor?->nombre_completo }}</strong>
    </div>
    <div class="col-md-6">
        <small class="text-muted d-block">Ayudantes</small>
        @forelse ($programacion->ayudantes as $ayudante)
            <span class="t-insignia t-turquesa">{{ $ayudante->nombre_completo }}</span>
        @empty
            <span class="text-muted">—</span>
        @endforelse
    </div>
    @if ($programacion->notes)
        <div class="col-12">
            <small class="text-muted d-block">Observaciones</small>
            {{ $programacion->notes }}
        </div>
    @endif
</div>

<h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-1"></i> Historial de cambios</h6>

@if ($cambios->isEmpty())
    <div class="t-vacio">
        <i class="bi bi-clock-history"></i>
        <h6>Sin movimientos registrados</h6>
    </div>
@else
    <div class="table-responsive">
        <table class="table t-tabla mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cambios as $cambio)
                    <tr>
                        <td><span class="t-fecha">{{ $cambio['fecha'] }}</span></td>
                        <td>{{ $cambio['usuario'] }}</td>
                        <td><span class="t-insignia t-{{ $colorAccion[$cambio['accion']] ?? 'gris' }}">{{ ucfirst($cambio['accion']) }}</span></td>
                        <td>{{ $cambio['detalle'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="t-pie-form">
    <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button>
</div>
