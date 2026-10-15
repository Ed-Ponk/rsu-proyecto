@extends('adminlte::page')

@section('title', 'Asistencias')

@section('content')
    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-calendar-check"></i></span>
                Listado de asistencias
            </h1>
            <p class="t-subtitulo">Por defecto se muestran las de hoy. Las asistencias deciden si un vehículo puede iniciar su recorrido.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn t-boton" id="botonNuevo"><i class="bi bi-plus-lg me-1"></i> Agregar asistencia</button>
        </div>
    </div>

    <div class="t-tarjeta mb-3">
        <div class="t-tarjeta-cuerpo">
            <div class="t-filtros">
                <div>
                    <label class="form-label fw-semibold small" for="filtroDesde">Fecha de inicio</label>
                    <input type="date" id="filtroDesde" class="form-control" value="{{ $hoy }}" max="{{ $hoy }}">
                </div>
                <div>
                    <label class="form-label fw-semibold small" for="filtroHasta">Fecha de fin</label>
                    <input type="date" id="filtroHasta" class="form-control" value="{{ $hoy }}" max="{{ $hoy }}">
                </div>
                <div>
                    <label class="form-label fw-semibold small" for="filtroTexto">Buscar empleado</label>
                    <input type="search" id="filtroTexto" class="form-control" placeholder="DNI, nombre o apellido...">
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn t-boton" id="botonFiltrar"><i class="bi bi-funnel-fill me-1"></i> Filtrar</button>
                    <button type="button" class="btn btn-outline-secondary rounded-3" id="botonHoy"><i class="bi bi-eraser me-1"></i> Hoy</button>
                </div>
            </div>
        </div>
    </div>

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Empleado</th>
                            <th>Cargo</th>
                            <th>Fecha y hora</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Turno</th>
                            <th>Origen</th>
                            <th>Notas</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-lg'])
@stop

@section('js')
    <script>
        const HOY = "{{ $hoy }}";
        const campo = (id) => document.getElementById(id);

        /* ---------- Formulario: al elegir persona/fecha/hora se pide una vista previa al servidor ---------- */
        function activarFormularioDeAsistencia(cuerpo) {
            const persona = cuerpo.querySelector('#asistenciaPersona');
            const personaEdicion = cuerpo.querySelector('#personaEnEdicion');
            const fecha = cuerpo.querySelector('#asistenciaFecha');
            const hora = cuerpo.querySelector('#asistenciaHora');
            const estado = cuerpo.querySelector('#asistenciaEstado');
            const ficha = cuerpo.querySelector('#fichaPersona');
            const tipo = cuerpo.querySelector('#asistenciaTipo');
            const turno = cuerpo.querySelector('#asistenciaTurno');
            const enEdicion = cuerpo.querySelector('form').dataset.edicion === '1';
            let controlador = null;

            async function actualizar() {
                const esAusencia = estado.value === 'ausente';
                tipo.value = esAusencia ? 'No aplica (ausencia)' : (tipo.dataset.actual || 'Automático');

                const idPersona = enEdicion ? personaEdicion.value : persona.value;
                if (!idPersona || !fecha.value || !hora.value) return ficha.classList.add('d-none');

                controlador?.abort();
                controlador = new AbortController();

                const parametros = new URLSearchParams({ user_id: idPersona, date: fecha.value, time: hora.value });
                let datos;

                try {
                    const respuesta = await fetch(`{{ route('admin.asistencias.vistaprevia') }}?${parametros}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: controlador.signal,
                    });
                    if (!respuesta.ok) return;
                    datos = await respuesta.json();
                } catch (error) {
                    return;   // se canceló por una consulta más reciente
                }

                const p = datos.persona;
                const marcas = datos.marcaciones.length
                    ? datos.marcaciones.map((m) => {
                        const color = m.estado === 'ausente' ? 'rojo' : (m.tipo === 'entrada' ? 'verde' : 'ambar');
                        return `<span class="t-insignia t-${color} me-1">${Tads.escapar(m.hora)} ${Tads.escapar(m.tipo || 'ausente')}</span>`;
                    }).join('')
                    : '<span class="text-muted">No hay registros para este día.</span>';

                let aviso = '';
                if (!p.tiene_contrato) {
                    aviso = '<div class="t-aviso t-aviso-alerta"><i class="bi bi-exclamation-triangle-fill me-1"></i>Sin contrato vigente en esa fecha: no se podrá registrar.</div>';
                } else if (!esAusencia) {
                    aviso = datos.tipo === 'entrada'
                        ? '<div class="t-aviso t-aviso-entrada"><i class="bi bi-box-arrow-in-right me-1"></i>Se registrará como ENTRADA.</div>'
                        : '<div class="t-aviso t-aviso-salida"><i class="bi bi-box-arrow-right me-1"></i>Se registrará como SALIDA.</div>';
                }

                ficha.classList.remove('d-none');
                ficha.innerHTML =
                    '<div class="t-datos">' +
                    `<div><i class="bi bi-person-fill me-1"></i><strong>${Tads.escapar(p.nombre)}</strong></div>` +
                    `<div><i class="bi bi-person-vcard me-1"></i>DNI: <strong>${Tads.escapar(p.dni)}</strong></div>` +
                    `<div><i class="bi bi-envelope me-1"></i>${Tads.escapar(p.correo)}</div>` +
                    `<div><i class="bi bi-telephone me-1"></i>${p.telefono ? Tads.escapar(p.telefono) : 'No registrado'}</div>` +
                    '</div>' +
                    `<div class="t-del-dia"><i class="bi bi-clock-history me-1"></i>Registros del día: ${marcas}</div>${aviso}`;

                if (!enEdicion) tipo.value = esAusencia ? 'No aplica (ausencia)' : (datos.tipo === 'entrada' ? 'Entrada' : 'Salida');
                turno.value = datos.turno_etiqueta || 'Sin turno para esa hora';
            }

            [persona, fecha, hora, estado].forEach((el) => el?.addEventListener('change', actualizar));
            [fecha, hora].forEach((el) => el.addEventListener('input', actualizar));
            actualizar();
        }

        const gestorAsistencias = new Tads.Gestor({
            entidad: 'asistencia',
            icono: 'bi-calendar-check',
            urlListado: "{{ route('admin.asistencias.index') }}",
            urlRecurso: "{{ url('admin/asistencias') }}",
            urlCrear: "{{ route('admin.asistencias.create') }}",
            orden: [],
            filtros: () => ({ desde: campo('filtroDesde').value, hasta: campo('filtroHasta').value, buscar: campo('filtroTexto').value }),
            alCargarFormulario: activarFormularioDeAsistencia,
            columnas: [
                { data: 'dni', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                { data: 'persona', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                { data: 'funcion', render: (v) => Tads.insignia(v, v === 'Conductor' ? 'azul' : 'turquesa') },
                { data: 'orden', render: (valor, modo, fila) => modo === 'display'
                    ? `<span class="t-fecha">${fila.fecha}<small>${fila.hora}</small></span>` : valor },
                { data: 'tipo', defaultContent: '', render: (v) => !v ? '<span class="text-muted">—</span>'
                    : (v === 'entrada' ? Tads.insignia('Entrada', 'verde') : Tads.insignia('Salida', 'ambar')) },
                { data: 'estado', render: (v) => v === 'presente' ? Tads.insignia('Presente', 'verde') : Tads.insignia('Ausente', 'rojo') },
                { data: 'turno', defaultContent: '', render: (v) => v ? Tads.insignia(v, 'azul') : '<span class="text-muted">—</span>' },
                { data: 'origen', render: (v) => v === 'marcacion' ? Tads.insignia('Marcación DNI', 'violeta') : Tads.insignia('Manual', 'gris') },
                { data: 'notas', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                { data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, `${fila.persona} · ${fila.fecha} ${fila.hora}`)) },
            ],
        });

        campo('botonFiltrar').addEventListener('click', () => gestorAsistencias.recargar());
        campo('filtroTexto').addEventListener('keyup', (e) => { if (e.key === 'Enter') gestorAsistencias.recargar(); });
        campo('filtroDesde').addEventListener('change', (e) => {
            if (campo('filtroHasta').value < e.target.value) campo('filtroHasta').value = e.target.value;
            campo('filtroHasta').min = e.target.value;
        });
        campo('botonHoy').addEventListener('click', () => {
            campo('filtroDesde').value = HOY;
            campo('filtroHasta').value = HOY;
            campo('filtroTexto').value = '';
            gestorAsistencias.recargar();
        });
    </script>
@stop
