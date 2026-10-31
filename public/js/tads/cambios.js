/**
 * Módulos de Gestión de cambios y dashboard de programación (Avance 04)
 *
 *   - Motivos: catálogo de motivos predefinidos para los cambios
 *   - Cambios: lista con filtros, detalle, cambio masivo y eliminación (que restablece los valores iniciales)
 *   - Dashboard de programación: por zona, programaciones completas/incompletas y cambio del personal faltante
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const primerError = (estado, json) => Tads.textoDeError(estado, json).split('\n')[0];

    /* ------------------------------------------------------------------ motivos */

    Tads.Modulos.motivos = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'motivo',
                icono: 'bi-chat-left-text-fill',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                buscar: 'Buscar motivo...',
                columnas: [
                    { data: 'nombre', render: (v, modo, fila) => modo === 'display'
                        ? `<strong>${Tads.escapar(v)}</strong>${fila.usos ? `<small class="d-block text-muted">Usado en ${fila.usos} ${fila.usos === 1 ? 'cambio' : 'cambios'}</small>` : ''}`
                        : v },
                    { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: 'actualizado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, fila.nombre)) },
                ],
            });
        },
    };

    /* ------------------------------------------------------------------ cambios */

    /** Ventana de confirmación del cambio masivo: lo que se va a hacer, antes de hacerlo. */
    function resumenDeCambio(r, total) {
        const f = (iso) => iso.split('-').reverse().join('/');

        return `
            <div class="av-resumen">
                <div class="t-resumen-grupo">
                    <div><small>Tipo de cambio</small><strong>${Tads.escapar(r.tipo)}</strong></div>
                    <div><small>Periodo</small><strong>${f(r.inicio)} → ${f(r.fin)}</strong></div>
                    <div><small>Zonas</small><strong>${r.zonas.length ? Tads.escapar(r.zonas.join(', ')) : 'Todas'}</strong></div>
                    <div><small>Programaciones</small><strong>${total}</strong></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6"><div class="t-nota"><small class="text-muted d-block">Sale</small><span class="antes">${Tads.escapar(r.saliente)}</span></div></div>
                    <div class="col-sm-6"><div class="t-nota"><small class="text-muted d-block">Entra</small><span class="despues">${Tads.escapar(r.entrante)}</span></div></div>
                </div>
                <div class="t-nota mb-3"><small class="text-muted d-block">Motivo</small>${Tads.escapar(r.motivo)}</div>
                <div class="t-nota t-nota-ambar">
                    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Revise antes de continuar:</strong>
                    se ${total === 1 ? 'cambiará 1 programación' : `cambiarán ${total} programaciones`} y quedarán como «Reprogramado».
                    Podrá deshacerlo eliminando el cambio desde la lista de cambios.
                </div>
            </div>`;
    }

    Tads.Modulos.cambios = {
        init: function (contenedor) {
            const campo = (id) => document.getElementById(id);
            const valoresIniciales = { desde: campo('filtroDesde').value, hasta: campo('filtroHasta').value };
            const urlRecurso = contenedor.dataset.urlRecurso;
            const colorTipo = { turno: 'violeta', vehiculo: 'azul', conductor: 'verde', ayudante: 'turquesa' };
            const valor = (v, clase) => `<div class="av-valor"><i class="bi bi-circle-fill ${clase}"></i><div>${clase === 'av-despues' ? '<strong>' : ''}${Tads.escapar(v.titulo)}${clase === 'av-despues' ? '</strong>' : ''}${v.detalle ? `<small>${Tads.escapar(v.detalle)}</small>` : ''}</div></div>`;

            const gestor = new Tads.Gestor({
                entidad: 'cambio masivo',
                icono: 'bi-arrow-left-right',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[1, 'desc']],
                porPagina: 10,
                buscar: 'Buscar en los cambios...',
                filtros: () => ({ desde: campo('filtroDesde').value, hasta: campo('filtroHasta').value, tipo: campo('filtroTipo').value }),
                alCargarFormulario: (cuerpo, g) => Tads.activarFormularioDeProgramacion?.(cuerpo, g),
                // El cambio masivo toca muchas programaciones a la vez: se valida y se pide confirmación antes de enviarlo.
                confirmarEnvio: async (formulario) => {
                    if (!formulario.hasAttribute('data-cambio-masivo')) return true;

                    const json = await formulario._validar();
                    if (!json || !json.ok) return false;

                    const decision = await Swal.fire({
                        title: 'Confirmar cambio masivo', html: resumenDeCambio(formulario._resumen(), json.total), width: 640, showCancelButton: true, reverseButtons: true,
                        confirmButtonText: '<i class="bi bi-check2-circle me-1"></i> Confirmar cambio', cancelButtonText: '<i class="bi bi-x-lg me-1"></i> Cancelar',
                        buttonsStyling: false, customClass: { confirmButton: 'btn t-boton mx-1', cancelButton: 'btn t-boton-cancelar mx-1' },
                    });

                    return decision.isConfirmed;
                },
                columnas: [
                    { data: 'tipo', render: (v, modo, fila) => modo === 'display'
                        ? `${Tads.insignia(fila.tipo_texto, colorTipo[v] ?? 'gris')}
                           <small class="d-block text-muted mt-1">Prog. #${fila.programacion} · ${Tads.escapar(fila.fecha_programacion ?? '')}</small>`
                        : fila.tipo_texto },
                    { data: 'orden', render: (v, modo, fila) => modo === 'display'
                        ? `<span class="t-fecha">${Tads.escapar(fila.fecha)}<small>${Tads.escapar(fila.hora)}</small></span>` : v },
                    { data: 'antes', render: (v, modo) => modo === 'display' ? valor(v, 'av-antes') : `${v.titulo} ${v.detalle}` },
                    { data: 'despues', render: (v, modo) => modo === 'display' ? valor(v, 'av-despues') : `${v.titulo} ${v.detalle}` },
                    { data: 'motivo', render: (v, modo, fila) => modo === 'display'
                        ? `${Tads.escapar(v)}${fila.descripcion ? `<small class="d-block text-muted">${Tads.escapar(fila.descripcion)}</small>` : ''}${fila.masivo ? `<div class="mt-1">${Tads.insignia('Masivo', 'azul')}</div>` : ''}`
                        : `${v} ${fila.descripcion ?? ''}` },
                    { data: 'usuario', render: (v, modo, fila) => modo === 'display'
                        ? `<div class="av-usuario"><i class="bi bi-person-gear"></i><div>${Tads.escapar(v)}<small>${Tads.escapar(fila.correo ?? '')}</small></div></div>`
                        : `${v} ${fila.correo ?? ''}` },
                    { data: null, orderable: false, searchable: false, className: 'text-center', render: (fila) => Tads.acciones(
                        `<button type="button" class="btn t-boton-icono t-info" data-detalle="${fila.id}" data-bs-toggle="tooltip" title="Ver detalle"><i class="bi bi-eye-fill"></i></button>`,
                        fila.puede_eliminar
                            ? `<button type="button" class="btn t-boton-icono t-eliminar" data-quitar="${Tads.escapar(fila.lote)}" data-cambio="${fila.id}" data-bs-toggle="tooltip" title="Eliminar y restablecer"><i class="bi bi-trash3-fill"></i></button>`
                            : Tads.botonBloqueado('bi-trash3-fill', 't-eliminar', 'La programación ya finalizó o pasó su fecha: el cambio ya no se puede deshacer'),
                    ) },
                ],
            });

            contenedor._gestor = gestor;

            campo('tabla').addEventListener('click', async (evento) => {
                const detalle = evento.target.closest('[data-detalle]');
                const quitar = evento.target.closest('[data-quitar]');

                if (detalle) {
                    bootstrap.Tooltip.getInstance(detalle)?.hide();
                    return gestor.abrirFormulario(`${contenedor.dataset.urlDetalle}/${detalle.dataset.detalle}`, '<i class="bi bi-arrow-left-right me-2"></i>Detalles del cambio');
                }

                if (!quitar) return;

                const decision = await Swal.fire({
                    title: '¿Eliminar este cambio?',
                    html: 'La programación recuperará el valor que tenía antes. Antes se comprueba que ese valor siga disponible.',
                    icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, eliminar y restablecer', cancelButtonText: 'Cancelar', reverseButtons: true,
                });
                if (!decision.isConfirmed) return;

                const { ok, estado, json } = await Tads.peticion(`${urlRecurso}/${quitar.dataset.quitar}?cambio_id=${quitar.dataset.cambio}`, { metodo: 'POST', cuerpo: Tads.cuerpoConMetodo('DELETE') });
                if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo eliminar el cambio');

                gestor.recargar();
                Tads.aviso(json.mensaje);
            });

            campo('botonFiltrar').addEventListener('click', () => gestor.recargar());
            campo('filtroTipo').addEventListener('change', () => gestor.recargar());
            campo('botonLimpiar').addEventListener('click', () => {
                campo('filtroDesde').value = valoresIniciales.desde;
                campo('filtroHasta').value = valoresIniciales.hasta;
                campo('filtroTipo').value = '';
                gestor.recargar();
            });
        },
    };

    /* ------------------------------------------------------------------ dashboard de programación */

    Tads.Modulos.dashboardProgramacion = {
        init: function (contenedor) {
            const campo = (id) => document.getElementById(id);
            const ventana = '#ventanaFormulario';
            const modal = document.querySelector(ventana);
            const cuerpoVentana = modal.querySelector('.modal-body');
            const tituloVentana = modal.querySelector('.modal-title');
            const filtros = () => new URLSearchParams({ fecha: campo('filtroFecha').value, turno_id: campo('filtroTurno').value });

            async function cargar() {
                const destino = campo('zonasDashboard');
                destino.innerHTML = '<div class="t-fichas-cargando"><span class="spinner-border spinner-border-sm me-2"></span>Cargando…</div>';

                const { ok, estado, json } = await Tads.peticion(`${contenedor.dataset.urlListado}?${filtros()}`);
                if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo cargar el dashboard');

                Object.entries(json.kpis).forEach(([clave, valor]) => {
                    const cifra = contenedor.querySelector(`[data-kpi="${clave}"] .fw-bold`);
                    if (cifra) cifra.textContent = String(valor);
                });

                if (!json.programaciones.length) {
                    destino.innerHTML = `
                        <div class="t-tarjeta" style="grid-column: 1 / -1"><div class="t-vacio">
                            <i class="bi bi-calendar-x"></i>
                            <h6>No hay programaciones ese día</h6>
                            <p>Cambie la fecha o el turno, o genere programaciones desde «Programación».</p>
                        </div></div>`;
                    return;
                }

                const colorEstado = { reprogramado: 'ambar', finalizado: 'verde' };

                destino.innerHTML = json.programaciones.map((p) => `
                    <article class="t-ficha-grupo av-tarjeta-zona ${p.completa ? 'completa' : 'incompleta'}">
                        <header>
                            <div>
                                <h3><i class="bi bi-geo-alt-fill"></i>${Tads.escapar(p.zona ?? 'Sin zona')}</h3>
                                <small>${Tads.escapar(p.turno ?? '—')} · ${Tads.escapar(p.grupo)}</small>
                            </div>
                            ${p.completa ? Tads.insignia('Completa', 'verde') : Tads.insignia('Incompleta', 'rojo')}
                        </header>
                        <dl>
                            <div><dt>Turno</dt><dd>${Tads.insignia(p.turno ?? '—', 'ambar')}</dd></div>
                            <div><dt>Vehículo</dt><dd><span class="t-codigo">${Tads.escapar(p.placa ?? '—')}</span></dd></div>
                            <div><dt>Grupo</dt><dd>${Tads.escapar(p.grupo)}</dd></div>
                            <div><dt>Estado</dt><dd>${Tads.insignia(p.estado_texto, colorEstado[p.estado] ?? 'azul')}</dd></div>
                        </dl>
                        <div class="av-asistencia">
                            <div class="si"><i class="bi bi-person-check-fill"></i><span><strong>${p.presentes}</strong>${p.futura ? 'Asignados' : 'Presentes'}</span></div>
                            <div class="no ${p.faltantes ? 'hay' : ''}"><i class="bi bi-person-x-fill"></i><span><strong>${p.faltantes}</strong>Faltantes</span></div>
                        </div>
                        <footer>
                            <span class="t-ficha-cuenta"><strong>${p.personas}</strong> ${p.personas === 1 ? 'persona' : 'personas'}<small>conductor y ayudantes</small></span>
                            <button type="button" class="btn t-boton btn-sm" data-ver-detalles="${p.id}"><i class="bi bi-eye-fill me-1"></i> Ver detalles</button>
                        </footer>
                    </article>`).join('');
            }

            /** «Ver detalles»: el editor de la programación, ya con el personal faltante elegido para reemplazarlo. */
            async function abrirEditor(id) {
                const { ok, estado, json, texto } = await Tads.peticion(`${contenedor.dataset.urlProgramaciones}/${id}/edit`, { esperaJson: false });
                if (!ok) return Tads.alertaDeError(estado, json, 'Esta programación ya no se puede editar');

                tituloVentana.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editor de programación';
                cuerpoVentana.innerHTML = texto;
                Tads.ventana(ventana).show();

                Tads.activarFormularioDeProgramacion(cuerpoVentana, {
                    recargar: cargar, tituloEl: tituloVentana, alConfirmarCierre: () => {},
                    abrirFormulario: () => abrirEditor(id),
                });
            }

            // Guardar desde el editor.
            modal.addEventListener('submit', async (evento) => {
                const formulario = evento.target;
                if (!formulario.matches('form.t-formulario')) return;
                evento.preventDefault();

                Tads.limpiarErrores(formulario);
                const boton = formulario.querySelector('[type="submit"]');
                const original = boton.innerHTML;
                boton.disabled = true;
                boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

                const { ok, estado, json } = await Tads.peticion(formulario.action, { metodo: 'POST', cuerpo: new FormData(formulario) });
                boton.innerHTML = original;

                if (ok) {
                    Tads.ventana(ventana).hide();
                    Tads.aviso(json?.mensaje ?? 'Programación actualizada');
                    return cargar();
                }

                boton.disabled = false;
                if (estado === 422) Tads.mostrarErrores(formulario, json);
                else Tads.alertaDeError(estado, json);
            });

            campo('zonasDashboard').addEventListener('click', (evento) => {
                const boton = evento.target.closest('[data-ver-detalles]');
                if (boton) abrirEditor(boton.dataset.verDetalles);
            });

            campo('botonActualizar').addEventListener('click', cargar);
            campo('filtroFecha').addEventListener('change', cargar);
            campo('filtroTurno').addEventListener('change', cargar);
            campo('botonHoy').addEventListener('click', () => {
                const h = new Date();
                campo('filtroFecha').value = `${h.getFullYear()}-${String(h.getMonth() + 1).padStart(2, '0')}-${String(h.getDate()).padStart(2, '0')}`;
                cargar();
            });

            modal.addEventListener('hidden.bs.modal', () => { cuerpoVentana.innerHTML = ''; });

            cargar();
        },
    };
})(window);
