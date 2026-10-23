/**
 * Módulo de Vacaciones (Tads)
 *
 * Listado de solicitudes con acciones de aprobar, rechazar, editar y eliminar (solo las pendientes se pueden
 * modificar). En el formulario, la fecha de fin se calcula sola con el inicio y los días solicitados.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const COLOR_ESTADO = { pendiente: 'ambar', aprobada: 'verde', rechazada: 'rojo' };

    /** Fecha de fin = inicio + días - 1 (corridos). Se arma con hora fija para evitar saltos por zona horaria. */
    function activarCalculoDeFin(cuerpo) {
        const dias = cuerpo.querySelector('#vacacionDias');
        const inicio = cuerpo.querySelector('#vacacionInicio');
        const fin = cuerpo.querySelector('#vacacionFin');
        if (!dias || !inicio || !fin) return;

        const calcular = () => {
            const n = parseInt(dias.value, 10);
            if (!inicio.value || !n || n < 1) { fin.value = ''; return; }
            const fecha = new Date(`${inicio.value}T12:00:00`);
            fecha.setDate(fecha.getDate() + n - 1);
            fin.value = fecha.toISOString().slice(0, 10);
        };

        [dias, inicio].forEach((el) => el.addEventListener('input', calcular));
        calcular();
    }

    Tads.Modulos.vacaciones = {
        init: function (contenedor) {
            const urlRecurso = contenedor.dataset.urlRecurso;

            const gestor = new Tads.Gestor({
                entidad: 'solicitud de vacaciones',
                femenino: true,
                icono: 'bi-airplane-fill',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [],
                alCargarFormulario: activarCalculoDeFin,
                columnas: [
                    { data: 'dni', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                    { data: 'empleado', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'solicitud', className: 'text-nowrap', render: Tads.ordenFecha },
                    { data: 'orden_inicio', className: 'text-nowrap dt-left', render: (valor, modo, fila) => modo === 'display' ? Tads.escapar(fila.inicio) : valor },
                    { data: 'fin', className: 'text-nowrap dt-left', render: Tads.ordenFecha },
                    { data: 'dias', render: (v) => Tads.insignia(v, 'azul') },
                    { data: 'estado', render: (v, t, fila) => Tads.insignia(fila.estado_texto, COLOR_ESTADO[v] ?? 'gris') },
                    { data: 'restantes', render: (v) => Tads.insignia(v, v > 0 ? 'gris' : 'rojo') },
                    { data: 'notas', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: null, orderable: false, searchable: false, className: 'text-center text-nowrap', render: (fila) => acciones(fila) },
                ],
            });

            contenedor._gestor = gestor;

            function acciones(fila) {
                const quien = `${fila.empleado} · ${fila.inicio}`;
                const ayuda = 'Solo las solicitudes pendientes se pueden modificar';
                const bloqueado = (icono, clase) => Tads.botonBloqueado(icono, clase, ayuda);

                if (!fila.pendiente) {
                    return Tads.acciones(
                        bloqueado('bi-check-lg', 't-exito'), bloqueado('bi-x-lg', 't-eliminar'),
                        bloqueado('bi-pencil-square', 't-editar'), bloqueado('bi-trash3-fill', 't-eliminar'),
                    );
                }

                return Tads.acciones(
                    `<button type="button" class="btn t-boton-icono t-exito" data-aprobar="${fila.id}" data-version="${fila.version}" data-nombre="${Tads.escapar(quien)}" data-bs-toggle="tooltip" title="Aprobar"><i class="bi bi-check-lg"></i></button>`,
                    `<button type="button" class="btn t-boton-icono t-eliminar" data-rechazar="${fila.id}" data-version="${fila.version}" data-nombre="${Tads.escapar(quien)}" data-bs-toggle="tooltip" title="Rechazar"><i class="bi bi-x-lg"></i></button>`,
                    Tads.botonEditar(fila.id),
                    Tads.botonEliminar(fila.id, quien),
                );
            }

            /* ---------- Aprobar / rechazar ---------- */
            async function resolver(id, nombre, accion, version) {
                const aprobar = accion === 'aprobar';
                const decision = await Swal.fire({
                    title: aprobar ? '¿Aprobar solicitud?' : '¿Rechazar solicitud?',
                    html: aprobar
                        ? `Se aprobará la solicitud de <strong>${Tads.escapar(nombre)}</strong> y se descontarán los días de su cupo del año.`
                        : `Se rechazará la solicitud de <strong>${Tads.escapar(nombre)}</strong>. No se descontarán días.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: aprobar ? '#16a34a' : '#dc3545',
                    confirmButtonText: aprobar ? 'Sí, aprobar' : 'Sí, rechazar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                });
                if (!decision.isConfirmed) return;

                const { ok, estado, json } = await Tads.peticion(`${urlRecurso}/${id}/${accion}`, {
                    metodo: 'POST',
                    cuerpo: (() => { const cuerpo = Tads.cuerpoConMetodo('PATCH'); cuerpo.append('version', version); return cuerpo; })(),
                });

                if (!ok) return Tads.alertaDeError(estado, json, `No se pudo ${aprobar ? 'aprobar' : 'rechazar'} la solicitud`);

                gestor.recargar();
                Tads.aviso(json.mensaje);
            }

            document.getElementById('tabla').addEventListener('click', (evento) => {
                const aprobar = evento.target.closest('[data-aprobar]');
                const rechazar = evento.target.closest('[data-rechazar]');

                if (aprobar) resolver(aprobar.dataset.aprobar, aprobar.dataset.nombre, 'aprobar', aprobar.dataset.version);
                else if (rechazar) resolver(rechazar.dataset.rechazar, rechazar.dataset.nombre, 'rechazar', rechazar.dataset.version);
            });
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
