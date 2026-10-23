@extends('layouts.admin')

@section('title', 'Marcación de asistencia')

@section('content')
    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-fingerprint"></i></span>
                Marcación de asistencia
            </h1>
            <p class="t-subtitulo">El personal registra su entrada o salida con su DNI y su clave. El tipo y el turno se asignan solos.</p>
        </div>
        <a href="{{ route('admin.asistencias.index') }}" class="btn btn-outline-secondary rounded-3"><i class="bi bi-list-check me-1"></i> Ver asistencias</a>
    </div>

    <div class="t-quiosco"
         data-tads-modulo="marcacion"
         data-url-estado="{{ route('admin.asistencias.marcacion.estado') }}"
         data-url-marcar="{{ route('admin.asistencias.marcar') }}">

        {{-- Reloj sincronizado con la hora del servidor --}}
        <div class="t-reloj-tarjeta">
            <span class="t-reloj-etiqueta"><i class="bi bi-broadcast me-1"></i> Hora del sistema</span>
            <div class="t-reloj" id="reloj">--:--:--</div>
            <div class="t-reloj-fecha" id="relojFecha">&nbsp;</div>
            <div><span class="t-reloj-turno" id="relojTurno"><i class="bi bi-clock me-1"></i> Consultando turno...</span></div>
        </div>

        <div class="d-flex flex-column gap-3">
            <div class="t-tarjeta">
                <div class="t-tarjeta-cuerpo">
                    <form id="formMarcacion" class="t-formulario" autocomplete="off" novalidate>
                        <div class="t-alerta-form"></div>

                        <div class="mb-3">
                            <label for="marcaDni" class="form-label t-obligatorio">DNI</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                                <input type="text" name="dni" id="marcaDni" class="form-control t-numeros-tabulares" inputmode="numeric"
                                    maxlength="8" placeholder="12345678" required pattern="\d{8}" autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="marcaClave" class="form-label t-obligatorio">Clave de marcación</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="marcaClave" class="form-control" placeholder="Su clave" required maxlength="60">
                                <button type="button" class="btn btn-outline-secondary" id="verClave" tabindex="-1" aria-label="Mostrar clave"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>

                        <button type="submit" class="btn t-boton btn-lg w-100 justify-content-center" id="botonMarcar">
                            <i class="bi bi-check2-circle me-1"></i> Marcar asistencia
                        </button>
                    </form>

                    {{-- Confirmación: se llena con la respuesta del servidor y se oculta sola --}}
                    <div class="t-resultado" id="resultadoMarcacion" role="status" aria-live="polite"></div>
                </div>
            </div>

            <div class="t-tarjeta">
                <div class="t-tarjeta-cuerpo">
                    <h6 class="t-seccion-titulo"><i class="bi bi-clock-history"></i> Últimas marcaciones de hoy</h6>
                    <ul class="t-ultimas" id="ultimasMarcaciones"><li class="text-muted">Cargando...</li></ul>
                </div>
            </div>
        </div>
    </div>
@stop
