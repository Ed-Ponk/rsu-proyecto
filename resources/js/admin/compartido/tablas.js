(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ------------------------------------------------------------------ Gestor */

    /**
     * new Tads.Gestor({
     *   entidad, icono,                // "marca", "bi-tags": textos del título de la ventana
     *   urlListado, urlRecurso, urlCrear,
     *   columnas, orden, filtros,      // columnas DataTables, orden inicial, función que devuelve parámetros extra
     *   alCargarFormulario(cuerpo),    // para activar comportamientos propios del formulario
     * })
     */
    Tads.Gestor = class {
        constructor(config) {
            this.config = config;
            this.selectorVentana = config.ventana ?? '#ventanaFormulario';
            this.ventanaEl = document.querySelector(this.selectorVentana);
            this.tituloEl = this.ventanaEl.querySelector('.modal-title');
            this.cuerpoEl = this.ventanaEl.querySelector('.modal-body');
            this.tablaEl = document.querySelector(config.tabla ?? '#tabla');

            this.#crearTabla();
            this.#enlazarEventos();
        }

        recargar() {
            this.tabla.ajax.reload(null, false);
        }

        #crearTabla() {
            const c = this.config;

            this.tabla = $(this.tablaEl).DataTable({
                // DataTables llama a esta función cada vez que necesita datos. Debe devolver undefined
                // (no una promesa), por eso el trabajo asíncrono vive en #cargarDatos / #cargarPagina.
                // Con config.servidor la paginación, el orden y la búsqueda las hace el servidor (para tablas enormes).
                ...(c.servidor ? { serverSide: true, searchDelay: 400 } : {}),
                ajax: (parametros, devolver) => { c.servidor ? this.#cargarPagina(parametros, devolver) : this.#cargarDatos(devolver); },
                columns: c.columnas,
                order: c.orden ?? [[0, 'asc']],
                pageLength: c.porPagina ?? 10,
                lengthChange: false,
                pagingType: 'simple_numbers',
                autoWidth: false,
                processing: true,
                language: Tads.idioma,
                drawCallback: () => {
                    this.tablaEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
                },
            });
        }

        /** Modo servidor: se pide solo la página visible, ya ordenada y filtrada, con los totales. */
        async #cargarPagina(parametros, devolver) {
            const c = this.config;
            const envio = new URLSearchParams(c.filtros ? c.filtros() : {});
            envio.set('draw', parametros.draw);
            envio.set('start', parametros.start);
            envio.set('length', parametros.length);
            envio.set('search[value]', parametros.search?.value ?? '');
            envio.set('order[0][column]', parametros.order?.[0]?.column ?? 0);
            envio.set('order[0][dir]', parametros.order?.[0]?.dir ?? 'asc');

            const { ok, estado, json } = await Tads.peticion(`${c.urlListado}?${envio}`);

            if (!ok) {
                Tads.alertaDeError(estado, json, 'No se pudo cargar el listado');
                return devolver({ draw: parametros.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
            }

            c.alCargarListado?.(json);
            devolver(json);
        }

        async #cargarDatos(devolver) {
            const c = this.config;
            const extra = c.filtros ? new URLSearchParams(c.filtros()).toString() : '';
            const { ok, estado, json } = await Tads.peticion(extra ? `${c.urlListado}?${extra}` : c.urlListado);

            if (!ok) {
                Tads.alertaDeError(estado, json, 'No se pudo cargar el listado');
                return devolver({ data: [] });
            }

            c.alCargarListado?.(json);
            devolver({ data: json.data });
        }

        #enlazarEventos() {
            const c = this.config;

            document.querySelector(c.botonNuevo ?? '#botonNuevo')?.addEventListener('click', () => {
                // femenino: true → "Nueva marca", "Nueva asistencia".
                this.abrirFormulario(c.urlCrear, `<i class="bi ${c.icono ?? 'bi-plus-circle'} me-2"></i>${c.femenino ? 'Nueva' : 'Nuevo'} ${c.entidad}`);
            });

            this.tablaEl.addEventListener('click', (evento) => {
                const editar = evento.target.closest('[data-editar]');
                const eliminar = evento.target.closest('[data-eliminar]');

                if (editar) {
                    this.abrirFormulario(
                        `${c.urlRecurso}/${editar.dataset.editar}/edit`,
                        `<i class="bi ${c.icono ?? 'bi-pencil-square'} me-2"></i>Editar ${c.entidad}`,
                    );
                } else if (eliminar) {
                    this.#confirmarEliminacion(eliminar.dataset.eliminar, eliminar.dataset.nombre, this.tabla.row(eliminar.closest('tr')).data()?.version);
                }
            });

            this.ventanaEl.addEventListener('submit', (evento) => {
                if (!evento.target.matches('form.t-formulario')) return;
                evento.preventDefault();
                this.#enviarFormulario(evento.target);
            });

            // Esc o "Cancelar" con cambios sin guardar: se pregunta antes de descartarlos.
            let hayCambios = false;
            let descartar = false;
            this.ventanaEl.addEventListener('show.bs.modal', () => { hayCambios = false; descartar = false; });
            this.ventanaEl.addEventListener('input', () => { hayCambios = true; });
            this.alConfirmarCierre = () => { descartar = true; };
            this.ventanaEl.addEventListener('hide.bs.modal', (evento) => {
                if (!hayCambios || descartar) return;
                evento.preventDefault();
                Swal.fire({
                    title: '¿Descartar los cambios?', text: 'Lo que escribió todavía no se guardó.', icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Sí, descartar', cancelButtonText: 'Seguir editando', reverseButtons: true,
                }).then((decision) => {
                    if (!decision.isConfirmed) return;
                    descartar = true;
                    Tads.ventana(this.selectorVentana).hide();
                });
            });

            // Al cerrar la ventana se vacía el cuerpo para no reutilizar un formulario viejo.
            this.ventanaEl.addEventListener('hidden.bs.modal', () => { this.cuerpoEl.innerHTML = ''; });
        }

        async abrirFormulario(url, titulo) {
            const { ok, estado, json, texto } = await Tads.peticion(url, { esperaJson: false });

            if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo abrir el formulario');

            this.tituloEl.innerHTML = titulo;
            this.cuerpoEl.innerHTML = texto;
            Tads.ventana(this.selectorVentana).show();
            Tads.iniciarBuscadores(this.cuerpoEl);
            Tads.iniciarVerificaciones(this.cuerpoEl);
            this.cuerpoEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
            this.config.alCargarFormulario?.(this.cuerpoEl, this);
        }

        async #enviarFormulario(formulario) {
            if (formulario.dataset.enviando === '1') return;
            Tads.limpiarErrores(formulario);

            if (!formulario.checkValidity()) return formulario.reportValidity();

            const boton = formulario.querySelector('[type="submit"]');
            const textoOriginal = boton.innerHTML;
            formulario.dataset.enviando = '1';
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

            const { ok, estado, json } = await Tads.peticion(formulario.action, { metodo: 'POST', cuerpo: new FormData(formulario) });

            delete formulario.dataset.enviando;
            boton.disabled = false;
            boton.innerHTML = textoOriginal;

            if (ok) {
                this.alConfirmarCierre?.();   // se guardó: cerrar sin preguntar
                Tads.ventana(this.selectorVentana).hide();
                this.recargar();
                this.config.alGuardar?.(json);
                return Tads.aviso(json?.mensaje ?? 'Operación realizada correctamente.');
            }

            if (estado === 422) Tads.mostrarErrores(formulario, json);
            else Tads.alertaDeError(estado, json);
        }

        async #confirmarEliminacion(id, nombre, version) {
            const c = this.config;

            const decision = await Swal.fire({
                title: `¿Eliminar ${c.entidad}?`,
                html: nombre
                    ? `Se eliminará <strong>${Tads.escapar(nombre)}</strong>. Esta acción no se puede deshacer.`
                    : 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            });

            if (!decision.isConfirmed) return;

            const { ok, estado, json } = await Tads.peticion(`${c.urlRecurso}/${id}`, {
                metodo: 'POST',
                cuerpo: (() => { const cuerpo = Tads.cuerpoConMetodo('DELETE'); cuerpo.append('version', version); return cuerpo; })(),
            });

            if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo eliminar');

            this.recargar();
            Tads.aviso(json?.mensaje ?? 'Registro eliminado');
        }
    };

})(window);
