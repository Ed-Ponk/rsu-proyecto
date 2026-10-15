/*
 * Gestor de listados y formularios del panel.
 *
 * Cada pantalla de administración (marcas, vehículos, contratos...) crea un Tads.Gestor que:
 *   - llena una tabla DataTables con el JSON que devuelve el controlador (paginación y búsqueda incluidas),
 *   - abre los formularios de crear/editar dentro de una ventana emergente de Bootstrap,
 *   - envía los datos con fetch (sin recargar la página) y pinta los errores de validación bajo cada campo,
 *   - pide confirmación con SweetAlert2 antes de eliminar.
 *
 * Requiere: Bootstrap 5 (bundle), jQuery + DataTables y SweetAlert2 (los carga AdminLTE desde config/adminlte.php).
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});

    /* ------------------------------------------------------------------ utilidades */

    const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    Tads.escapar = function (valor) {
        const nodo = document.createElement('div');
        nodo.textContent = valor ?? '';
        return nodo.innerHTML;
    };

    Tads.idioma = {
        processing: 'Cargando...',
        search: 'Buscar:',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Mostrando 0 a 0 de 0 registros',
        infoFiltered: '(filtrado de _MAX_ registros)',
        loadingRecords: 'Cargando...',
        zeroRecords: 'No se encontraron resultados',
        emptyTable: 'No hay registros para mostrar',
        paginate: { first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último' },
    };

    /** Mensaje emergente en la esquina (éxito por defecto). */
    Tads.aviso = function (mensaje, icono = 'success') {
        Swal.fire({
            toast: true, position: 'top-end', icon: icono, title: mensaje,
            showConfirmButton: false, timer: 2600, timerProgressBar: true,
        });
    };

    /** Convierte una respuesta de error en un texto legible. */
    Tads.textoDeError = function (estado, json) {
        if (json?.errors) {
            return Object.values(json.errors).map((lista) => lista[0]).join('\n');
        }
        if (estado === 419) return 'Su sesión expiró. Recargue la página e inicie sesión otra vez.';
        if (estado === 403) return 'No tiene permiso para realizar esta acción.';

        return json?.mensaje || json?.message || 'Ocurrió un error inesperado. Intente nuevamente.';
    };

    Tads.alertaDeError = function (estado, json, titulo = 'No se pudo completar la acción') {
        Swal.fire({ title: titulo, text: Tads.textoDeError(estado, json), icon: 'error', confirmButtonText: 'Entendido' });
    };

    /** fetch con las cabeceras que espera Laravel; siempre resuelve con { ok, estado, json }. */
    Tads.peticion = async function (url, { metodo = 'GET', cuerpo = null, esperaJson = true } = {}) {
        const cabeceras = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token() };
        if (esperaJson) cabeceras.Accept = 'application/json';

        try {
            const respuesta = await fetch(url, { method: metodo, body: cuerpo, headers: cabeceras, credentials: 'same-origin' });
            const texto = await respuesta.text();
            let json = null;
            try { json = JSON.parse(texto); } catch (e) { /* la respuesta era HTML */ }

            return { ok: respuesta.ok, estado: respuesta.status, json, texto };
        } catch (error) {
            return { ok: false, estado: 0, json: { mensaje: 'No hay conexión con el servidor.' }, texto: '' };
        }
    };

    /** Cuerpo de formulario para simular PUT/PATCH/DELETE de Laravel. */
    Tads.cuerpoConMetodo = function (metodo) {
        const datos = new FormData();
        datos.append('_method', metodo);
        datos.append('_token', token());
        return datos;
    };

    Tads.ventana = (selector) => bootstrap.Modal.getOrCreateInstance(document.querySelector(selector));

    /* ---------- errores de validación bajo cada campo ---------- */

    Tads.limpiarErrores = function (formulario) {
        formulario.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        formulario.querySelectorAll('.invalid-feedback.t-error').forEach((el) => el.remove());
        const alerta = formulario.querySelector('.t-alerta-form');
        if (alerta) { alerta.classList.remove('visible'); alerta.textContent = ''; }
    };

    Tads.mostrarErrores = function (formulario, json) {
        const errores = json?.errors ?? {};
        const sueltos = [];
        let primero = null;

        Object.entries(errores).forEach(([clave, mensajes]) => {
            const base = clave.split('.')[0];
            const campo = [...formulario.querySelectorAll(`[name="${clave}"], [name="${base}"], [name="${base}[]"]`)]
                .find((el) => el.type !== 'hidden');

            if (!campo) { sueltos.push(mensajes[0]); return; }

            campo.classList.add('is-invalid');
            const referencia = campo.closest('.input-group') ?? campo;
            const aviso = document.createElement('div');
            aviso.className = 'invalid-feedback t-error d-block';
            aviso.textContent = mensajes[0];
            referencia.after(aviso);
            primero ??= campo;
        });

        if (!Object.keys(errores).length && (json?.mensaje || json?.message)) sueltos.push(json.mensaje || json.message);

        const alerta = formulario.querySelector('.t-alerta-form');
        if (sueltos.length && alerta) {
            alerta.textContent = sueltos.join(' ');
            alerta.classList.add('visible');
        }

        primero?.focus();
    };

    /* ---------- piezas de HTML que se repiten en las columnas ---------- */

    Tads.insignia = (texto, color = 'gris') => `<span class="t-insignia t-${color}">${Tads.escapar(texto)}</span>`;

    Tads.celdaFecha = function (valor) {
        if (!valor) return '<span class="text-muted">—</span>';
        const [dia, hora] = String(valor).split(' ');
        return `<span class="t-fecha">${Tads.escapar(dia)}${hora ? `<small>${Tads.escapar(hora)}</small>` : ''}</span>`;
    };

    Tads.acciones = (...botones) => `<div class="t-acciones">${botones.join('')}</div>`;

    Tads.botonEditar = (id) =>
        `<button type="button" class="btn t-boton-icono t-editar" data-editar="${id}" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil-square"></i></button>`;

    /** Si se pasa un motivo, el botón queda deshabilitado y el motivo se ve como ayuda. */
    Tads.botonEliminar = function (id, nombre, motivoBloqueo = '') {
        if (motivoBloqueo) {
            return `<span data-bs-toggle="tooltip" title="${Tads.escapar(motivoBloqueo)}"><button type="button" class="btn t-boton-icono t-eliminar" disabled><i class="bi bi-trash3-fill"></i></button></span>`;
        }
        return `<button type="button" class="btn t-boton-icono t-eliminar" data-eliminar="${id}" data-nombre="${Tads.escapar(nombre ?? '')}" data-bs-toggle="tooltip" title="Eliminar"><i class="bi bi-trash3-fill"></i></button>`;
    };

    /** Vista previa de una imagen elegida en un <input type="file"> dentro de su caja punteada. */
    Tads.vistaPreviaArchivo = function (caja, input) {
        caja.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            const archivo = input.files?.[0];
            if (!archivo) return;
            const lector = new FileReader();
            lector.onload = (e) => {
                caja.innerHTML = `<div><img src="${e.target.result}" alt="Vista previa"><small>${Tads.escapar(archivo.name)}</small></div>`;
            };
            lector.readAsDataURL(archivo);
        });
    };

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
                // (no una promesa), por eso el trabajo asíncrono vive en #cargarDatos.
                ajax: (_parametros, devolver) => { this.#cargarDatos(devolver); },
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

        async #cargarDatos(devolver) {
            const c = this.config;
            const extra = c.filtros ? new URLSearchParams(c.filtros()).toString() : '';
            const { ok, estado, json } = await Tads.peticion(extra ? `${c.urlListado}?${extra}` : c.urlListado);

            if (!ok) {
                Tads.alertaDeError(estado, json, 'No se pudo cargar el listado');
                return devolver({ data: [] });
            }

            devolver({ data: json.data });
        }

        #enlazarEventos() {
            const c = this.config;

            document.querySelector(c.botonNuevo ?? '#botonNuevo')?.addEventListener('click', () => {
                this.abrirFormulario(c.urlCrear, `<i class="bi ${c.icono ?? 'bi-plus-circle'} me-2"></i>Nuevo ${c.entidad}`);
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
                    this.#confirmarEliminacion(eliminar.dataset.eliminar, eliminar.dataset.nombre);
                }
            });

            this.ventanaEl.addEventListener('submit', (evento) => {
                if (!evento.target.matches('form.t-formulario')) return;
                evento.preventDefault();
                this.#enviarFormulario(evento.target);
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
            this.config.alCargarFormulario?.(this.cuerpoEl, this);
        }

        async #enviarFormulario(formulario) {
            Tads.limpiarErrores(formulario);

            if (!formulario.checkValidity()) return formulario.reportValidity();

            const boton = formulario.querySelector('[type="submit"]');
            const textoOriginal = boton.innerHTML;
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

            const { ok, estado, json } = await Tads.peticion(formulario.action, { metodo: 'POST', cuerpo: new FormData(formulario) });

            boton.disabled = false;
            boton.innerHTML = textoOriginal;

            if (ok) {
                Tads.ventana(this.selectorVentana).hide();
                this.recargar();
                this.config.alGuardar?.(json);
                return Tads.aviso(json?.mensaje ?? 'Operación realizada correctamente.');
            }

            if (estado === 422) Tads.mostrarErrores(formulario, json);
            else Tads.alertaDeError(estado, json);
        }

        async #confirmarEliminacion(id, nombre) {
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
                cuerpo: Tads.cuerpoConMetodo('DELETE'),
            });

            if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo eliminar');

            this.recargar();
            Tads.aviso(json?.mensaje ?? 'Registro eliminado');
        }
    };
})(window);
