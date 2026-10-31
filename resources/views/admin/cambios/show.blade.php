@php
    $colorEstado = ['finalizado' => 'verde', 'reprogramado' => 'ambar'];
@endphp

<div class="t-resumen-grupo">
    <div><small>Tipo de cambio</small><strong>{{ $cambio['tipo_texto'] }}</strong></div>
    <div><small>Hecho por</small><strong>{{ $cambio['usuario'] }}</strong></div>
    <div><small>Fecha del cambio</small><strong>{{ $cambio['fecha'] }}</strong></div>
    <div><small>Motivo</small><strong>{{ $cambio['motivo'] }}</strong></div>
    <div><small>Programaciones</small><strong>{{ $cambio['programaciones'] }}</strong></div>
    <div><small>Rango</small><strong>{{ $cambio['primera'] }}{{ $cambio['primera'] !== $cambio['ultima'] ? ' → '.$cambio['ultima'] : '' }}</strong></div>
</div>

@if ($cambio['descripcion'])
    <div class="mb-3">
        <small class="text-muted d-block">Detalle del motivo</small>
        {{ $cambio['descripcion'] }}
    </div>
@endif

<h6 class="fw-bold mb-2"><i class="bi bi-list-check me-1"></i> Valores previos y nuevos por programación</h6>

<div class="table-responsive">
    <table class="table t-tabla mb-0">
        <thead>
            <tr>
                <th>N.º</th>
                <th>Fecha</th>
                <th>Zona</th>
                <th>Grupo</th>
                <th>Turno</th>
                <th>Estado</th>
                <th>Cambió</th>
                <th>Valor previo</th>
                <th>Valor nuevo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td><span class="t-codigo">#{{ $fila->programming_id }}</span></td>
                    <td><span class="t-fecha">{{ $fila->programacion?->date->format('d/m/Y') }}</span></td>
                    <td>{{ $fila->programacion?->zona?->name }}</td>
                    <td>{{ $fila->programacion?->grupo?->name ?? '—' }}</td>
                    <td>{{ $fila->programacion?->turno?->name }}</td>
                    <td><span class="t-insignia t-{{ $colorEstado[$fila->programacion?->status] ?? 'azul' }}">{{ \App\Http\Controllers\Admin\CambioController::estadoTexto($fila->programacion) }}</span></td>
                    <td><span class="t-insignia t-ambar">{{ $fila->tipo_texto }}</span></td>
                    <td>{{ $fila->old_label }}</td>
                    <td><strong>{{ $fila->new_label }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="t-pie-form">
    <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button>
</div>
