(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
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
            bootstrap.Tooltip.getOrCreateInstance(elegido.querySelector('[data-cambiar-eleccion]'));
            abrir(false);
            if (!silencioso) avisarCambio();
        }

        function limpiar() {
            oculto.value = '';
            campo.value = '';
            elegido.hidden = true;
            entrada.hidden = false;
            marcarValidez();
            avisarCambio();
            campo.focus();
        }

        campo.addEventListener('input', () => {
            if (oculto.value) { oculto.value = ''; avisarCambio(); }
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


})(window);
