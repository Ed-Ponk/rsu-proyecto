@php
    // Mismos colores que el listado de contratos (contratos.js).
    $colorEstado = ['Vigente' => 'verde', 'Vencido' => 'rojo', 'Inactivo' => 'gris', 'Por iniciar' => 'azul'];
    $colorTipo = ['temporal' => 'ambar', 'nombrado' => 'violeta', 'permanente' => 'azul'];
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
                @unless ($persona->status)
                    · <span class="t-insignia t-rojo">Inactivo</span>
                @endunless
            </small>
        </div>

        <div class="t-historial-resumen">
            <div>
                <span class="t-historial-cifra">{{ $persona->contratos->count() }}</span>
                <small>{{ $persona->contratos->count() === 1 ? 'contrato' : 'contratos' }}</small>
            </div>
            <div>
                @if ($vigente)
                    <span class="t-insignia t-verde"><i class="bi bi-check-circle-fill me-1"></i>Con contrato vigente</span>
                @else
                    <span class="t-insignia t-ambar"><i class="bi bi-exclamation-circle-fill me-1"></i>Sin contrato vigente</span>
                @endif
            </div>
        </div>
    </div>

    @if ($persona->contratos->isEmpty())
        <div class="t-vacio">
            <i class="bi bi-file-earmark-x"></i>
            <h6>Esta persona aún no tiene contratos</h6>
            <p>Registre uno desde el módulo de contratos para que pueda programarse y marcar asistencia.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table t-tabla mb-0">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Salario</th>
                        <th>Prueba</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($persona->contratos as $contrato)
                        <tr @class(['t-fila-destacada' => $vigente?->is($contrato)])>
                            <td><span class="t-insignia t-{{ $colorTipo[$contrato->type] ?? 'gris' }}">{{ $tipos[$contrato->type] ?? $contrato->type }}</span></td>
                            <td><span class="t-fecha">{{ $contrato->start_date->format('d/m/Y') }}</span></td>
                            <td>
                                @if ($contrato->end_date)
                                    <span class="t-fecha">{{ $contrato->end_date->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><span class="t-codigo">S/ {{ number_format((float) $contrato->salary, 2) }}</span></td>
                            <td>
                                @if ($contrato->probation_months)
                                    {{ $contrato->probation_months }} {{ $contrato->probation_months === 1 ? 'mes' : 'meses' }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><span class="t-insignia t-{{ $colorEstado[$contrato->estado] ?? 'gris' }}">{{ $contrato->estado }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="t-pie-form">
    <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button>
    <a href="{{ route('admin.contratos.index') }}" class="btn t-boton"><i class="bi bi-file-earmark-text me-1"></i> Ir a contratos</a>
</div>
