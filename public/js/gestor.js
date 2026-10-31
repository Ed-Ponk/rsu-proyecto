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

    /** Escapa texto para HTML, incluidas las comillas: el resultado también es seguro dentro de un atributo ("...") o ('...'). */
    Tads.escapar = function (valor) {
        const nodo = document.createElement('div');
        nodo.textContent = valor ?? '';
        return nodo.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };

    Tads.idioma = {
        processing: 'Cargando...',
        search: '',
        searchPlaceholder: 'Buscar en la lista...',
        lengthMenu: 'Mostrar _MENU_',
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

    let avisoDeSesion = false;

    /** La sesión caducó (401/419): se avisa una sola vez y se lleva al usuario al inicio de sesión. */
    Tads.sesionExpirada = function () {
        if (avisoDeSesion) return;
        avisoDeSesion = true;
        Swal.fire({
            title: 'Su sesión expiró', text: 'Por seguridad debe iniciar sesión de nuevo para continuar.', icon: 'info',
            confirmButtonText: 'Iniciar sesión', allowOutsideClick: false, allowEscapeKey: false,
        }).then(() => { window.location.href = '/login'; });
    };

    Tads.alertaDeError = function (estado, json, titulo = 'No se pudo completar la acción') {
        if (estado === 401 || estado === 419) return Tads.sesionExpirada();

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

            if (respuesta.status === 401 || respuesta.status === 419) Tads.sesionExpirada();

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
            const candidatos = [...formulario.querySelectorAll(`[name="${clave}"], [name="${base}"], [name="${base}[]"]`)];
            // En un buscador el valor viaja en un campo oculto: el error se marca en su caja de texto.
            const oculto = candidatos.find((el) => el.type === 'hidden' && el.closest('.t-buscador'));
            const campo = candidatos.find((el) => el.type !== 'hidden')
                ?? oculto?.closest('.t-buscador').querySelector('input[type="search"]');

            if (!campo) { sueltos.push(...mensajes); return; }

            campo.classList.add('is-invalid');
            // Un grupo de casillas (días, grupos) marca su contenedor: el mensaje va debajo de todo el grupo, no dentro de una casilla.
            const referencia = campo.closest('[data-error-destino]') ?? campo.closest('.t-buscador') ?? campo.closest('.input-group') ?? campo;
            const aviso = document.createElement('div');
            aviso.className = 'invalid-feedback t-error d-block';
            aviso.textContent = mensajes[0];
            referencia.after(aviso);
            primero ??= campo;
        });

        if (!Object.keys(errores).length && (json?.mensaje || json?.message)) sueltos.push(json.mensaje || json.message);

        const alerta = formulario.querySelector('.t-alerta-form');
        if (sueltos.length && alerta) {
            alerta.textContent = sueltos.join(' · ');
            alerta.classList.add('visible');
        }

        primero?.focus();
    };

    /* ---------- piezas de HTML que se repiten en las columnas ---------- */

    Tads.insignia = (texto, color = 'gris') => `<span class="t-insignia t-${color}">${Tads.escapar(texto)}</span>`;

    /** Duración legible: 45 → "45 min", 60 → "1 h", 90 → "1 h 30 min", 120 → "2 h". */
    Tads.duracion = function (minutos) {
        const total = Math.max(0, Math.round(Number(minutos) || 0));
        if (total < 60) return `${total} min`;

        const horas = Math.floor(total / 60);
        const resto = total % 60;
        return resto ? `${horas} h ${resto} min` : `${horas} h`;
    };

    /** Etiqueta de puntualidad de una ENTRADA: tiempo de tardanza (lo calcula el backend) o "A tiempo". */
    Tads.insigniaTardanza = (minutos) => (minutos > 0
        ? Tads.insignia(`Tardanza ${Tads.duracion(minutos)}`, 'rojo')
        : Tads.insignia('A tiempo', 'verde'));

    /** "18/10/2026 09:30" → "2026-10-18 09:30": esa forma sí se ordena como texto igual que por calendario. */
    Tads.fechaIso = function (texto) {
        const partes = String(texto ?? '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}:\d{2}))?/);
        return partes ? `${partes[3]}-${partes[2]}-${partes[1]}${partes[4] ? ` ${partes[4]}` : ''}` : '';
    };

    /** Para columnas de fecha en texto plano: se muestra tal cual y se ordena por calendario. */
    Tads.ordenFecha = (valor, modo) => (modo === 'sort' || modo === 'type' ? Tads.fechaIso(valor) : (valor ?? ''));

    Tads.celdaFecha = function (valor, modo) {
        if (modo === 'sort' || modo === 'type') return Tads.fechaIso(valor);
        if (!valor) return '<span class="text-muted">—</span>';
        const [dia, hora] = String(valor).split(' ');
        return `<span class="t-fecha">${Tads.escapar(dia)}${hora ? `<small>${Tads.escapar(hora)}</small>` : ''}</span>`;
    };

    Tads.acciones = (...botones) => `<div class="t-acciones">${botones.join('')}</div>`;

    Tads.botonEditar = (id) =>
        `<button type="button" class="btn t-boton-icono t-editar" data-editar="${id}" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil-square"></i></button>`;

    /**
     * Botón que no se puede usar ahora. No lleva `disabled` (así sigue recibiendo foco): se marca con aria-disabled,
     * su nombre accesible es el motivo y el motivo también sale como ayuda al pasar el mouse o con el teclado.
     */
    Tads.botonBloqueado = (icono, clase, motivo) =>
        `<button type="button" class="btn t-boton-icono ${clase} t-bloqueado" aria-disabled="true" aria-label="${Tads.escapar(motivo)}" data-bs-toggle="tooltip" title="${Tads.escapar(motivo)}"><i class="bi ${icono}"></i></button>`;

    /** Si se pasa un motivo, el botón queda bloqueado y el motivo se ve como ayuda. */
    Tads.botonEliminar = function (id, nombre, motivoBloqueo = '') {
        if (motivoBloqueo) return Tads.botonBloqueado('bi-trash3-fill', 't-eliminar', motivoBloqueo);

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

    /* ------------------------------------------------------------------ Buscador */

    /** Resalta en el texto (ya escapado) las palabras buscadas. */
    function resaltar(texto, palabras) {
        let html = Tads.escapar(texto);
        palabras.forEach((palabra) => {
            const patron = Tads.escapar(palabra).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            html = html.replace(new RegExp(`(${patron})`, 'gi'), '<mark>$1</mark>');
        });
        return html;
    }

    /** forma "avatar" (foto circular de persona) o "logo" (imagen completa en un recuadro, p. ej. marcas). */
    function avatarDe(item, forma = 'avatar') {
        // Un color se muestra como muestra de su código hexadecimal, no como foto ni iniciales.
        if (item.color) return `<span class="t-avatar t-avatar-color" style="background:${Tads.escapar(item.color)}"></span>`;

        const clase = forma === 'logo' ? 't-avatar t-avatar-logo' : 't-avatar';
        return item.foto
            ? `<img class="${clase}" src="${Tads.escapar(item.foto)}" alt="">`
            : `<span class="${clase}">${Tads.escapar(item.iniciales ?? '')}</span>`;
    }

    /**
     * Convierte un .t-buscador[data-tads-buscador] en un campo de búsqueda con sugerencias del servidor.
     * El controlador responde { data: [{ id, titulo, detalle, foto, iniciales, insignia, insignia_elegido }] }
     * (insignia = { texto, color } o null). El id elegido viaja en el input oculto, así que el backend sigue
     * validando como con un combo; al elegir o limpiar, el oculto emite "change" para quien lo escuche.
     *
     * Atributos opcionales del contenedor: data-mensaje, data-sin-coincidencias (":texto" = lo buscado),
     * data-vacio, data-cambiar (ayuda del botón), data-forma ("avatar" | "logo") y data-inicial (JSON del valor elegido).
     */
    Tads.buscador = function (raiz) {
        if (raiz._tadsBuscador) return;
        raiz._tadsBuscador = true;

        const oculto = raiz.querySelector('input[type="hidden"]');
        const entrada = raiz.querySelector('.t-buscador-entrada');
        const campo = entrada.querySelector('input');
        const lista = raiz.querySelector('.t-buscador-lista');
        const elegido = document.createElement('div');
        elegido.className = 't-buscador-elegido';
        elegido.hidden = true;
        raiz.appendChild(elegido);

        // Textos propios de cada buscador (personal, marcas...), declarados en el HTML.
        const textos = {
            requerido: raiz.dataset.mensaje || 'Seleccione una opción de la lista.',
            sinCoincidencias: raiz.dataset.sinCoincidencias || 'No hay resultados que coincidan con «:texto».',
            vacio: raiz.dataset.vacio || 'No hay registros.',
            cambiar: raiz.dataset.cambiar || 'Cambiar',
        };
        const forma = raiz.dataset.forma || 'avatar';

        // Buscador dependiente (ej. modelo según la marca): envía el valor de otro buscador y se reinicia si este cambia.
        const dependencia = raiz.dataset.depende ? raiz.closest('form')?.querySelector(raiz.dataset.depende) : null;
        const textoLibre = campo.placeholder;
        const revisarDependencia = () => {
            if (!dependencia) return;
            const lista = !!dependencia.value;
            campo.disabled = !lista;
            campo.placeholder = lista ? textoLibre : (raiz.dataset.sinDependencia || textoLibre);
        };

        let resultados = [];
        let activo = -1;
        let espera = null;
        let turno = 0;

        const marcarValidez = () => campo.setCustomValidity(oculto.value ? '' : textos.requerido);
        const avisarCambio = () => oculto.dispatchEvent(new Event('change', { bubbles: true }));
        const insigniaDe = (i) => (i ? Tads.insignia(i.texto, i.color) : '');

        const ficha = (item, palabras = []) => `
            ${avatarDe(item, forma)}
            <span class="t-buscador-texto">
                <strong>${resaltar(item.titulo, palabras)}</strong>
                ${item.detalle ? `<small>${resaltar(item.detalle, palabras)}</small>` : ''}
            </span>`;

        function abrir(abierta) {
            lista.hidden = !abierta;
            campo.setAttribute('aria-expanded', String(abierta));
        }

        function pintar(texto) {
            const palabras = texto.split(/\s+/).filter(Boolean);
            activo = resultados.length ? 0 : -1;

            lista.innerHTML = resultados.length
                ? resultados.map((item, i) => `
                    <li class="t-buscador-opcion${i === activo ? ' activa' : ''}" role="option" id="${lista.id}-${i}" data-indice="${i}" aria-selected="${i === activo}">
                        ${ficha(item, palabras)}
                        ${insigniaDe(item.insignia)}
                    </li>`).join('')
                : `<li class="t-buscador-vacio"><i class="bi bi-search"></i>${texto
                    ? Tads.escapar(textos.sinCoincidencias.replace(':texto', texto))
                    : Tads.escapar(textos.vacio)}</li>`;

            campo.setAttribute('aria-activedescendant', activo >= 0 ? `${lista.id}-${activo}` : '');
            abrir(true);
        }

        async function buscar() {
            const texto = campo.value.trim();
            const miTurno = ++turno;

            lista.innerHTML = '<li class="t-buscador-vacio"><span class="spinner-border spinner-border-sm"></span>Buscando...</li>';
            abrir(true);

            const url = new URL(raiz.dataset.url, window.location.href);
            url.searchParams.set('q', texto);
            if (dependencia) url.searchParams.set(raiz.dataset.parametro, dependencia.value);
            const { ok, estado, json } = await Tads.peticion(url.toString());
            if (miTurno !== turno) return;   // llegó tarde: ya hay una búsqueda más nueva

            if (!ok) {
                abrir(false);
                return Tads.alertaDeError(estado, json, 'No se pudo buscar');
            }

            resultados = json.data ?? [];
            pintar(texto);
        }

        function moverA(indice) {
            if (!resultados.length) return;
            activo = (indice + resultados.length) % resultados.length;
            lista.querySelectorAll('.t-buscador-opcion').forEach((op, i) => {
                op.classList.toggle('activa', i === activo);
                op.setAttribute('aria-selected', String(i === activo));
            });
            lista.querySelector('.activa')?.scrollIntoView({ block: 'nearest' });
            campo.setAttribute('aria-activedescendant', `${lista.id}-${activo}`);
        }

        /** silencioso: al precargar un valor (formulario de edición) no se emite "change". */
        function elegir(item, silencioso = false) {
            raiz._tadsItem = item;   // quien escuche "change" puede leer los datos extra del elegido (ej. la capacidad de un vehículo)
            oculto.value = item.id;
            campo.value = item.titulo;
            campo.classList.remove('is-invalid');
            raiz.parentElement.querySelectorAll('.t-error').forEach((el) => el.remove());
            marcarValidez();

            elegido.innerHTML = `
                ${ficha(item)}
                ${insigniaDe(item.insignia_elegido ?? item.insignia)}
                <button type="button" class="btn t-boton-icono t-neutro" data-cambiar-eleccion data-bs-toggle="tooltip" title="${Tads.escapar(textos.cambiar)}"><i class="bi bi-arrow-repeat"></i></button>`;
            elegido.hidden = false;
            entrada.hidden = true;
            const botonCambiar = elegido.querySelector('[data-cambiar-eleccion]');
            bootstrap.Tooltip.getOrCreateInstance(botonCambiar);
            abrir(false);
            // El campo de texto se oculta: sin esto el foco cae al <body> y Esc deja de cerrar la ventana.
            if (!silencioso) botonCambiar.focus();
            if (!silencioso) avisarCambio();
        }

        function limpiar() {
            raiz._tadsItem = null;
            oculto.value = '';
            campo.value = '';
            elegido.hidden = true;
            entrada.hidden = false;
            marcarValidez();
            avisarCambio();
            campo.focus();
        }

        // Para elegir o limpiar desde código (ej. al traer los datos de un grupo de personal).
        raiz.tadsElegir = (item) => (item ? elegir(item) : limpiar());

        campo.addEventListener('input', () => {
            if (oculto.value) { raiz._tadsItem = null; oculto.value = ''; avisarCambio(); }
            marcarValidez();
            clearTimeout(espera);
            espera = setTimeout(buscar, 250);
        });

        campo.addEventListener('focus', () => { if (!oculto.value) buscar(); });

        campo.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); lista.hidden ? buscar() : moverA(activo + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moverA(activo - 1); }
            else if (e.key === 'Enter' && !lista.hidden) { e.preventDefault(); if (resultados[activo]) elegir(resultados[activo]); }
            else if (e.key === 'Escape' && !lista.hidden) { e.stopPropagation(); abrir(false); }
        });

        // mousedown en lugar de click: se adelanta al blur del campo.
        lista.addEventListener('mousedown', (e) => {
            const opcion = e.target.closest('.t-buscador-opcion');
            if (!opcion) return;
            e.preventDefault();
            elegir(resultados[Number(opcion.dataset.indice)]);
        });

        elegido.addEventListener('click', (e) => {
            const boton = e.target.closest('[data-cambiar-eleccion]');
            if (!boton) return;
            bootstrap.Tooltip.getInstance(boton)?.dispose();
            limpiar();
        });

        campo.addEventListener('blur', () => setTimeout(() => abrir(false), 120));

        // Valor ya elegido (al editar): llega como JSON con la misma forma que los resultados.
        if (raiz.dataset.inicial) {
            try { elegir(JSON.parse(raiz.dataset.inicial), true); } catch (e) { /* JSON inválido: se ignora */ }
        }

        if (dependencia) {
            // Si se cambia la marca, el modelo elegido ya no corresponde: se limpia y se vuelve a habilitar.
            dependencia.addEventListener('change', () => {
                if (oculto.value) {
                    oculto.value = '';
                    campo.value = '';
                    elegido.hidden = true;
                    entrada.hidden = false;
                    avisarCambio();
                }
                revisarDependencia();
                marcarValidez();
            });
            revisarDependencia();
        }

        marcarValidez();
    };

    Tads.iniciarBuscadores = (raiz) => raiz.querySelectorAll('[data-tads-buscador]').forEach(Tads.buscador);

    /* ------------------------------------------------------------------ Reloj del servidor */

    /**
     * Reloj que muestra la hora del SERVIDOR (la que se guarda en las marcaciones), no la de la PC.
     * Consulta { servidor, zona, turno, ... } cada cierto tiempo, mide el desfase y avanza solo cada segundo.
     *   Tads.relojDelServidor({ url, hora, fecha, turno, formatoFecha, alEstado(json) })
     * hora/fecha/turno son elementos (opcionales) donde se pinta; alEstado recibe la respuesta completa.
     */
    Tads.relojDelServidor = function ({ url, hora, fecha, turno, formatoFecha = 'larga', cadaMs = 30000, alEstado }) {
        let desfase = 0;
        let fmtHora = null;
        let fmtFecha = null;

        function tic() {
            if (!fmtHora) return;
            const ahora = new Date(Date.now() + desfase);
            if (hora) hora.textContent = fmtHora.format(ahora);
            if (fecha) {
                const texto = fmtFecha.format(ahora);
                fecha.textContent = texto.charAt(0).toUpperCase() + texto.slice(1);
            }
        }

        async function consultar() {
            const enviado = Date.now();
            const { ok, json } = await Tads.peticion(url);
            if (!ok || !json) return;

            // Se descuenta la mitad del viaje de ida y vuelta para no adelantar ni atrasar el reloj.
            desfase = new Date(json.servidor).getTime() + (Date.now() - enviado) / 2 - Date.now();

            if (!fmtHora) {
                fmtHora = new Intl.DateTimeFormat('es-PE', { timeZone: json.zona, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
                fmtFecha = new Intl.DateTimeFormat('es-PE', formatoFecha === 'corta'
                    ? { timeZone: json.zona, weekday: 'long', day: 'numeric', month: 'long' }
                    : { timeZone: json.zona, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            }
            tic();

            if (turno) {
                turno.innerHTML = json.turno
                    ? `<i class="bi bi-clock me-1"></i> Turno ${Tads.escapar(json.turno)}`
                    : '<i class="bi bi-moon-stars me-1"></i> Fuera de turno';
            }
            alEstado?.(json);
        }

        consultar();
        setInterval(tic, 1000);
        setInterval(consultar, cadaMs);

        return { consultar };
    };

    /* ------------------------------------------------------------------ Verificación en vivo */

    /**
     * Valida contra el servidor mientras se escribe. El contenedor [data-tads-verificar="url"] agrupa los campos
     * marcados con data-verificar; con cada cambio se envían sus valores y el controlador responde
     * { errores: { campo: mensaje } } usando las mismas reglas que al guardar. El botón Guardar sigue validando igual.
     */
    Tads.verificacionEnVivo = function (contenedor) {
        if (contenedor._tadsVerificar) return;
        contenedor._tadsVerificar = true;

        const campos = [...contenedor.querySelectorAll('[data-verificar]')];
        let espera = null;
        let turno = 0;

        // El error se pinta en la caja visible: en un buscador es su campo de texto, no el oculto.
        const visibleDe = (campo) => (campo.type === 'hidden'
            ? campo.closest('.t-buscador')?.querySelector('input[type="search"]')
            : campo);

        function pintar(campo, mensaje, completo) {
            const visible = visibleDe(campo);
            if (!visible) return;
            const referencia = visible.closest('.t-buscador') ?? visible.closest('.input-group') ?? visible;

            // Quita lo que haya dejado una verificación anterior o el último intento de guardar.
            let siguiente = referencia.nextElementSibling;
            while (siguiente?.classList.contains('t-error')) {
                const borrar = siguiente;
                siguiente = siguiente.nextElementSibling;
                borrar.remove();
            }
            visible.classList.remove('is-invalid', 'is-valid');

            if (mensaje) {
                visible.classList.add('is-invalid');
                const aviso = document.createElement('div');
                aviso.className = 'invalid-feedback t-error d-block';
                aviso.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${Tads.escapar(mensaje)}`;
                referencia.after(aviso);
            } else if (completo && campo.type !== 'hidden' && campo.value.trim()) {
                // Solo se confirma en verde cuando hay contexto completo (p. ej. ya hay marca elegida).
                visible.classList.add('is-valid');
            }
        }

        async function verificar() {
            const miTurno = ++turno;
            const url = new URL(contenedor.dataset.tadsVerificar, window.location.href);
            campos.forEach((c) => url.searchParams.set(c.name, c.value));

            const { ok, json } = await Tads.peticion(url.toString());
            if (!ok || miTurno !== turno) return;   // error de red o respuesta vieja: no se toca nada

            const errores = json?.errores ?? {};
            const completo = campos.every((c) => c.type !== 'hidden' || c.value);
            campos.forEach((c) => pintar(c, errores[c.name], completo));
        }

        const programar = () => { clearTimeout(espera); espera = setTimeout(verificar, 350); };

        campos.forEach((campo) => {
            campo.addEventListener('input', programar);
            campo.addEventListener('change', programar);   // el buscador avisa así al elegir una marca
        });

        // Al editar ya hay valores: se marcan desde el inicio.
        if (campos.some((c) => c.value.trim())) verificar();
    };

    Tads.iniciarVerificaciones = (raiz) => raiz.querySelectorAll('[data-tads-verificar]').forEach(Tads.verificacionEnVivo);

    /* ------------------------------------------------------------------ Gestor */

    /**
     * new Tads.Gestor({
     *   entidad, icono,                // "marca", "bi-tags": textos del título de la ventana
     *   urlListado, urlRecurso, urlCrear,
     *   columnas, orden, filtros,      // columnas DataTables, orden inicial, función que devuelve parámetros extra
     *   alCargarFormulario(cuerpo),    // para activar comportamientos propios del formulario
     *   confirmarEnvio(formulario),    // opcional: promesa que devuelve false para cancelar el envío
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
                // Barra de herramientas: buscador grande con lupa a la izquierda y «Mostrar N» a la derecha (se apilan en el móvil).
                layout: { topStart: 'search', topEnd: 'pageLength', bottomStart: 'info', bottomEnd: 'paging' },
                lengthMenu: [10, 15, 25, 50, 100],
                pagingType: 'simple_numbers',
                autoWidth: false,
                processing: true,
                language: { ...Tads.idioma, searchPlaceholder: c.buscar ?? Tads.idioma.searchPlaceholder },
                drawCallback: () => {
                    this.tablaEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => bootstrap.Tooltip.getOrCreateInstance(el));
                },
            });

            if (c.exportar) this.#crearBotonesDeExportar(c.exportar);
        }

        /**
         * Botones Excel / PDF / Imprimir / Columnas (extensión Buttons de DataTables).
         * config.exportar = { contenedor: '#selector', titulo: 'Programaciones', columnas: [0, 1, ...] }
         * Las columnas devuelven texto plano cuando no es "display", así lo exportado no lleva HTML.
         */
        #crearBotonesDeExportar(opciones) {
            const destino = document.querySelector(opciones.contenedor);
            if (!destino || !$.fn.dataTable.Buttons) return;

            const exportOptions = { columns: opciones.columnas };
            const boton = (extend, icono, texto, clase, extra = {}) => ({
                extend, text: `<i class="bi ${icono} me-1"></i>${texto}`, className: `btn btn-sm ${clase}`,
                title: opciones.titulo, exportOptions, ...extra,
            });

            new $.fn.dataTable.Buttons(this.tabla, {
                buttons: [
                    boton('excelHtml5', 'bi-file-earmark-excel-fill', 'Excel', 't-excel'),
                    boton('pdfHtml5', 'bi-file-earmark-pdf-fill', 'PDF', 't-pdf', { orientation: 'landscape' }),
                    boton('print', 'bi-printer-fill', 'Imprimir', 't-imprimir'),
                    { extend: 'colvis', text: '<i class="bi bi-layout-three-columns me-1"></i>Columnas', className: 'btn btn-sm t-columnas', columns: opciones.columnas },
                ],
            });

            this.tabla.buttons().container().appendTo(destino);
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
                    this.#confirmarEliminacion(eliminar.dataset.eliminar, eliminar.dataset.nombre);
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
            Tads.limpiarErrores(formulario);

            if (!formulario.checkValidity()) return formulario.reportValidity();

            // Operaciones delicadas piden confirmación antes de enviar (config.confirmarEnvio devuelve una promesa de true/false).
            if (this.config.confirmarEnvio && !(await this.config.confirmarEnvio(formulario))) return;

            const boton = formulario.querySelector('[type="submit"]');
            const textoOriginal = boton.innerHTML;
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

            const { ok, estado, json } = await Tads.peticion(formulario.action, { metodo: 'POST', cuerpo: new FormData(formulario) });

            boton.disabled = false;
            boton.innerHTML = textoOriginal;

            if (ok) {
                this.alConfirmarCierre?.();   // se guardó: cerrar sin preguntar
                Tads.ventana(this.selectorVentana).hide();
                this.recargar();
                this.config.alGuardar?.(json);
                Tads.aviso(json?.mensaje ?? 'Operación realizada correctamente.');

                // Se guardó, pero hay algo que conviene revisar (por ejemplo, programaciones pendientes afectadas).
                if (json?.aviso) {
                    Swal.fire({ icon: 'warning', title: 'Revise esto', text: json.aviso, confirmButtonText: 'Entendido' });
                }

                return;
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
