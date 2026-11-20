@php
    $colorTipo = ['turno' => 'violeta', 'vehiculo' => 'azul', 'conductor' => 'verde', 'ayudante' => 'turquesa'][$cambio->type] ?? 'gris';
@endphp

<div class="t-resumen-grupo">
    <div><small>Cambio</small><strong>#{{ $cambio->id }}</strong></div>
    <div><small>Tipo</small><strong><span class="t-insignia t-{{ $colorTipo }}">{{ $cambio->tipo_texto }}</span></strong></div>
    <div><small>Realizado por</small><strong>{{ $cambio->usuario?->name ?? 'Sistema' }}</strong></div>
    <div><small>Fecha del cambio</small><strong>{{ $cambio->created_at?->format('d/m/Y H:i') }}</strong></div>
    <div><small>Programación</small><strong>#{{ $cambio->programming_id }}</strong></div>
    <div><small>Origen</small><strong>{{ $cambio->scope === 'masivo' ? 'Cambio masivo' : 'Cambio individual' }}</strong></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="av-cambio-valor">
            <div class="t-info-cabecera neutra"><i class="bi bi-arrow-left-circle me-2"></i>Valores anteriores</div>
            <dl>
                @foreach ($antes['filas'] as [$rotulo, $valor])
                    <div><dt>{{ $rotulo }}</dt><dd>{{ $valor }}</dd></div>
                @endforeach
            </dl>
        </div>
    </div>
    <div class="col-md-6">
        <div class="av-cambio-valor">
            <div class="t-info-cabecera"><i class="bi bi-arrow-right-circle me-2"></i>Valores nuevos</div>
            <dl>
                @foreach ($despues['filas'] as [$rotulo, $valor])
                    <div><dt>{{ $rotulo }}</dt><dd>{{ $valor }}</dd></div>
                @endforeach
            </dl>
        </div>
    </div>
</div>

<h6 class="t-seccion-titulo"><i class="bi bi-calendar2-check"></i> Programación afectada</h6>
<div class="t-resumen-grupo">
    <div><small>Fecha</small><strong>{{ $cambio->programacion?->date->format('d/m/Y') }}</strong></div>
    <div><small>Zona</small><strong>{{ $cambio->programacion?->zona?->name ?? '—' }}</strong></div>
    <div><small>Grupo</small><strong>{{ $cambio->programacion?->grupo?->name ?? '—' }}</strong></div>
    <div><small>Turno actual</small><strong>{{ $cambio->programacion?->turno?->name ?? '—' }}</strong></div>
</div>

<div class="t-nota">
    <div class="fw-semibold mb-1"><i class="bi bi-chat-left-text-fill me-1 text-muted"></i> Motivo del cambio</div>
    <strong>{{ $cambio->reason_name ?? 'Motivo propio' }}</strong>@if ($cambio->description) — {{ $cambio->description }}@endif
</div>

<div class="t-pie-form">
    @if ($hermanos > 1)
        <span class="me-auto small text-muted"><i class="bi bi-diagram-3 me-1"></i>Parte de una operación de {{ $hermanos }} cambios.</span>
    @endif
    <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button>
</div>
