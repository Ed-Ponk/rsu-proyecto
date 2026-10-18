/**
 * Módulo de Asistencias (Tads)
 *
 * Administra el listado DataTables de asistencias diarias, filtros reactivos
 * por fechas/texto con atajo "Hoy" y formulario de registro dinámico con
 * cálculo de turno/tipo y vista previa reactiva (AbortController).
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    Tads.Modulos.asistencias = {
        init: function (contenedor) {
            const HOY = contenedor.dataset.hoy || new Date().toISOString().split('T')[0];
            const urlListado = contenedor.dataset.urlListado;
            const urlRecurso = contenedor.dataset.urlRecurso;
            const urlCrear = contenedor.dataset.urlCrear;
            const urlVistaPrevia = contenedor.dataset.urlVistaPrevia;

            const campo = (id) => document.getElementById(id);

            /* ---------- Formulario: al elegir persona/fecha/hora se pide una vista previa al servidor ---------- */
            function activarFormularioDeAsistencia(cuerpo) {
                // El buscador guarda el id en este campo oculto y emite "change" al elegir o limpiar.
                const persona = cuerpo.querySelector('#asistenciaPersonaId');
                const personaEdicion = cuerpo.querySelector('#personaEnEdicion');
                const fecha = cuerpo.querySelector('#asistenciaFecha');
                const hora = cuerpo.querySelector('#asistenciaHora');
                const estado = cuerpo.querySelector('#asistenciaEstado');
                const ficha = cuerpo.querySelector('#fichaPersona');
                const tipo = cuerpo.querySelector('#asistenciaTipo');
                const turno = cuerpo.querySelector('#asistenciaTurno');
                const formulario = cuerpo.querySelector('form');
                const enEdicion = formulario?.dataset.edicion === '1';
                let controlador = null;

                async function actualizar() {
                    if (!tipo || !ficha) return;
                    const esAusencia = estado ? estado.value === 'ausente' : false;
                    tipo.value = esAusencia ? 'No aplica (ausencia)' : (tipo.dataset.actual || 'Automático');

                    const idPersona = enEdicion ? (personaEdicion?.value) : (persona?.value);
                    if (!idPersona || !fecha?.value || !hora?.value) return ficha.classList.add('d-none');

                    controlador?.abort();
                    controlador = new AbortController();

                    const parametros = new URLSearchParams({ user_id: idPersona, date: fecha.value, time: hora.value });
                    let datos;

                    try {
                        const respuesta = await fetch(`${urlVistaPrevia}?${parametros}`, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            signal: controlador.signal,
                        });
                        if (!respuesta.ok) return;
                        datos = await respuesta.json();
                    } catch (error) {
                        return;   // se canceló por una consulta más reciente
                    }

                    const p = datos.persona;
                    const marcas = (datos.marcaciones && datos.marcaciones.length)
                        ? datos.marcaciones.map((m) => {
                            const color = m.estado === 'ausente' ? 'rojo' : (m.tipo === 'entrada' ? 'verde' : 'ambar');
                            const tarde = m.tipo === 'entrada' && m.tardanza > 0 ? ` · ${m.tardanza} min tarde` : '';
                            return `<span class="t-insignia t-${color} me-1">${Tads.escapar(m.hora)} ${Tads.escapar(m.tipo || 'ausente')}${tarde}</span>`;
                        }).join('')
                        : '<span class="text-muted">No hay registros para este día.</span>';

                    // Lo que se registraría lo decide el backend (turno, entrada/salida, tardanza o por qué no se puede).
                    let aviso = '';
                    if (p.de_vacaciones) {
                        aviso = '<div class="t-aviso t-aviso-alerta"><i class="bi bi-umbrella-fill me-1"></i>Está de vacaciones aprobadas en esa fecha: no se podrá registrar.</div>';
                    } else if (!p.tiene_contrato) {
                        aviso = '<div class="t-aviso t-aviso-alerta"><i class="bi bi-exclamation-triangle-fill me-1"></i>Sin contrato vigente en esa fecha: no se podrá registrar.</div>';
                    } else if (!esAusencia && datos.problema) {
                        aviso = `<div class="t-aviso t-aviso-alerta"><i class="bi bi-x-octagon-fill me-1"></i>${Tads.escapar(datos.problema)}</div>`;
                    } else if (!esAusencia) {
                        const tarde = datos.tardanza > 0 ? ` con <strong>${datos.tardanza} min de tardanza</strong>` : ' a tiempo';
                        aviso = datos.tipo === 'entrada'
                            ? `<div class="t-aviso ${datos.tardanza > 0 ? 't-aviso-alerta' : 't-aviso-entrada'}"><i class="bi bi-box-arrow-in-right me-1"></i>Se registrará como ENTRADA${tarde}.</div>`
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

                    if (!enEdicion && tipo) {
                        tipo.value = esAusencia ? 'No aplica (ausencia)'
                            : (!datos.tipo ? 'Turno completo' : (datos.tipo === 'entrada' ? 'Entrada' : 'Salida'));
                    }
                    if (turno) turno.value = datos.turno_etiqueta || 'Sin turno para esa hora';
                }

                [persona, fecha, hora, estado].forEach((el) => el?.addEventListener('change', actualizar));
                [fecha, hora].forEach((el) => el?.addEventListener('input', actualizar));
                actualizar();
            }

            const gestorAsistencias = new Tads.Gestor({
                entidad: 'asistencia',
                femenino: true,
                icono: 'bi-calendar-check',
                urlListado: urlListado,
                urlRecurso: urlRecurso,
                urlCrear: urlCrear,
                orden: [],
                filtros: () => ({
                    desde: campo('filtroDesde')?.value || '',
                    hasta: campo('filtroHasta')?.value || '',
                    buscar: campo('filtroTexto')?.value || '',
                }),
                alCargarFormulario: activarFormularioDeAsistencia,
                columnas: [
                    { data: 'dni', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                    { data: 'persona', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'funcion', render: (v) => Tads.insignia(v, v === 'Conductor' ? 'azul' : 'turquesa') },
                    { data: 'orden', render: (valor, modo, fila) => modo === 'display'
                        ? `<span class="t-fecha">${fila.fecha}<small>${fila.hora}</small></span>` : valor },
                    { data: 'tipo', defaultContent: '', render: (v, modo, fila) => !v ? '<span class="text-muted">—</span>'
                        : (v === 'entrada'
                            ? `<span class="d-inline-flex flex-wrap gap-1">${Tads.insignia('Entrada', 'verde')}${Tads.insigniaTardanza(fila.tardanza)}</span>`
                            : Tads.insignia('Salida', 'ambar')) },
                    { data: 'estado', render: (v) => v === 'presente' ? Tads.insignia('Presente', 'verde') : Tads.insignia('Ausente', 'rojo') },
                    { data: 'turno', defaultContent: '', render: (v) => v ? Tads.insignia(v, 'azul') : '<span class="text-muted">—</span>' },
                    { data: 'origen', render: (v) => v === 'marcacion' ? Tads.insignia('Marcación DNI', 'violeta') : Tads.insignia('Manual', 'gris') },
                    { data: 'notas', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, `${fila.persona} · ${fila.fecha} ${fila.hora}`)) },
                ],
            });

            contenedor._gestor = gestorAsistencias;

            const btnFiltrar = campo('botonFiltrar');
            if (btnFiltrar) btnFiltrar.addEventListener('click', () => gestorAsistencias.recargar());

            const fTexto = campo('filtroTexto');
            if (fTexto) fTexto.addEventListener('keyup', (e) => { if (e.key === 'Enter') gestorAsistencias.recargar(); });

            const fDesde = campo('filtroDesde');
            const fHasta = campo('filtroHasta');
            if (fDesde && fHasta) {
                fDesde.addEventListener('change', (e) => {
                    if (fHasta.value < e.target.value) fHasta.value = e.target.value;
                    fHasta.min = e.target.value;
                });
            }

            const btnHoy = campo('botonHoy');
            if (btnHoy && fDesde && fHasta && fTexto) {
                btnHoy.addEventListener('click', () => {
                    fDesde.value = HOY;
                    fHasta.value = HOY;
                    fTexto.value = '';
                    gestorAsistencias.recargar();
                });
            }
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
