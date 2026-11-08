/**
 * Orquestador Tads Admin (Auto-Discovery)
 *
 * Escanea el DOM buscando contenedores con el atributo [data-tads-modulo],
 * resolviendo el controlador adecuado e inicializándolo con sus atributos declarativos.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    Tads.iniciarModulos = function (raiz) {
        const contexto = raiz || document;
        contexto.querySelectorAll('[data-tads-modulo]').forEach((contenedor) => {
            if (contenedor._tadsIniciado) return;
            const nombre = contenedor.dataset.tadsModulo;
            const modulo = Tads.Modulos[nombre];
            if (modulo && typeof modulo.init === 'function') {
                contenedor._tadsIniciado = true;
                modulo.init(contenedor);
                Tads.aplicarPermisos(contenedor);
            }
        });
    };

    /**
     * Oculta los botones que el usuario no puede usar (el servidor igual los rechaza con 403, esto solo evita el tropiezo).
     * El contenedor del módulo declara `data-permiso` (prefijo, ej. «colors») y `data-permisos` («*» o la lista de códigos).
     */
    Tads.aplicarPermisos = function (contenedor) {
        const prefijo = contenedor.dataset.permiso;
        const lista = contenedor.dataset.permisos;
        if (!prefijo || lista === undefined || lista === '*') return;

        const tiene = new Set(lista.split(',').filter(Boolean));
        const puede = (codigo) => tiene.has(codigo);
        const ocultar = (selector, codigo, ambito) => {
            if (puede(codigo)) return;
            (ambito || contenedor).querySelectorAll(selector).forEach((el) => { el.hidden = true; el.style.display = 'none'; });
        };

        const aplicar = () => {
            ocultar('[data-editar], [data-aprobar], [data-rechazar], [data-cambiar], [data-finalizar]', `${prefijo}.edit`);
            ocultar('[data-eliminar], [data-quitar]', `${prefijo}.delete`);
            ocultar('[data-imagenes]', 'vehicles.manage-images');
            ocultar('[data-contratos]', 'contracts.view');
            ocultar('[data-vacaciones]', 'vacations.view');
        };

        ocultar('#botonNuevo', `${prefijo}.create`, document);
        aplicar();
        // Cada vez que la tabla se redibuja (búsqueda, página, recarga) llegan botones nuevos.
        contenedor.addEventListener('draw.dt', aplicar, true);
        if (window.jQuery) window.jQuery(contenedor).on('draw.dt', aplicar);
    };

    // "Cerrar sesión" del menú lateral: el cierre de sesión es un POST con CSRF, no un enlace.
    document.addEventListener('click', (evento) => {
        const enlace = evento.target.closest('a[href$="#cerrar-sesion"]');
        if (!enlace) return;

        evento.preventDefault();
        const formulario = document.createElement('form');
        formulario.method = 'POST';
        formulario.action = '/logout';
        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        formulario.appendChild(token);
        document.body.appendChild(formulario);
        formulario.submit();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => Tads.iniciarModulos());
    } else {
        Tads.iniciarModulos();
    }
})(window);
