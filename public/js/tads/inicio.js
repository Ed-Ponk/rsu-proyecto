/**
 * Módulo de Inicio (Tads)
 *
 * Reloj del banner de bienvenida: hora del servidor, fecha y turno vigente (lo decide el backend),
 * usando el mismo componente que el quiosco de Marcación DNI.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    Tads.Modulos.inicio = {
        init: function (contenedor) {
            Tads.relojDelServidor({
                url: contenedor.dataset.urlEstado,
                hora: contenedor.querySelector('[data-reloj-hora]'),
                fecha: contenedor.querySelector('[data-reloj-fecha]'),
                turno: contenedor.querySelector('[data-reloj-turno]'),
                formatoFecha: 'corta',
                cadaMs: 60000,
            });
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
