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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => Tads.iniciarModulos());
    } else {
        Tads.iniciarModulos();
    }
})(window);
