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
            }
        });
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
