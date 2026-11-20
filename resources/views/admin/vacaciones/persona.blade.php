@php
    $colorEstado = ['pendiente' => 'ambar', 'aprobada' => 'verde', 'rechazada' => 'rojo'];
    $iniciales = mb_strtoupper(mb_substr($persona->name, 0, 1).mb_substr((string) $persona->last_name, 0, 1));
@endphp

<div class="t-historial">
    <div class="t-historial-persona">
        @if ($persona->url_foto)
            <img class="t-avatar t-avatar-lg" src="{{ $persona->url_foto }}" alt="Foto">
        @else
            <span class="t-avatar t-avatar-lg">{{ $iniciales }}</span>
        @endif

        <div class="t-historial-datos">
            <strong>{{ $persona->nombre_completo }}</strong>
            <small>
                DNI <span class="t-codigo">{{ $persona->dni }}</span>
                · <span class="t-insignia t-turquesa">{{ $persona->tipo?->name ?? 'Sin función' }}</span>
            </small>
        </div>

        <div class="t-historial-resumen">
            <div>
                <span class="t-historial-cifra">{{ $disponibles }}</span>
                <small>de {{ $cupo }} días disponibles en {{ $anio }}</small>
            </div>
            <div>
                @if ($enCurso)
                    <span class="t-insignia t-azul"><i class="bi bi-airplane-fill me-1"></i>De vacaciones ahora</span>
                @else
                    <span class="t-insignia t-gris">{{ $aprobados }} día(s) aprobados en {{ $anio }}</span>
                @endif
            </div>
        </div>
    </div>

    @if ($vacaciones->isEmpty())
        <div class="t-vacio">
            <i class="bi bi-airplane"></i>
            <h6>Esta persona aún no tiene solicitudes de vacaciones</h6>
            <p>Registre una desde el módulo de vacaciones.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table t-tabla mb-0">
                <thead>
                    <tr>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Días</th>
                        <th>Estado</th>
                        <th>Notas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vacaciones as $vacacion)
                        <tr @class(['t-fila-destacada' => $vacacion->status === 'aprobada' && $vacacion->start_date->lte(today()) && $vacacion->end_date->gte(today())])>
                            <td><span class="t-fecha">{{ $vacacion->start_date->format('d/m/Y') }}</span></td>
                            <td><span class="t-fecha">{{ $vacacion->end_date->format('d/m/Y') }}</span></td>
                            <td><span class="t-insignia t-azul">{{ $vacacion->days }}</span></td>
                            <td><span class="t-insignia t-{{ $colorEstado[$vacacion->status] ?? 'gris' }}">{{ \App\Models\Vacacion::ESTADOS[$vacacion->status] ?? $vacacion->status }}</span></td>
                            <td>{{ $vacacion->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="t-pie-form">
    <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button>
    <a href="{{ route('admin.vacaciones.index') }}" class="btn t-boton"><i class="bi bi-airplane-fill me-1"></i> Ir a vacaciones</a>
</div>
