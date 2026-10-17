/**
 * Módulo de Autenticación y Acceso (Tads)
 *
 * Administra las interacciones de login, recuperación de clave y 2FA:
 *   - Alternador de visibilidad de contraseñas [data-ver-clave]
 *   - Protección contra doble envío de formularios [data-un-envio]
 *   - Alternador dinámico de método 2FA (código TOTP vs código de recuperación)
 */
(function () {
    'use strict';

    function inicializarAuth() {
        // Botón del "ojo": muestra u oculta la contraseña del campo indicado en data-ver-clave.
        document.querySelectorAll('[data-ver-clave]').forEach((boton) => {
            if (boton._tadsEnlazado) return;
            boton._tadsEnlazado = true;
            boton.addEventListener('click', () => {
                const campo = document.getElementById(boton.dataset.verClave);
                if (!campo) return;
                const mostrar = campo.type === 'password';
                campo.type = mostrar ? 'text' : 'password';
                boton.innerHTML = `<i class="bi ${mostrar ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
            });
        });

        // Evita enviar dos veces el mismo formulario.
        document.querySelectorAll('form[data-un-envio]').forEach((formulario) => {
            if (formulario._tadsEnlazado) return;
            formulario._tadsEnlazado = true;
            formulario.addEventListener('submit', () => {
                const boton = formulario.querySelector('button[type=submit]');
                if (boton) {
                    boton.disabled = true;
                    boton.textContent = 'Procesando...';
                }
            });
        });

        // Alternador de 2FA en pantalla de Two-Factor Challenge
        const enlace2FA = document.getElementById('cambiarMetodo');
        if (enlace2FA && !enlace2FA._tadsEnlazado) {
            enlace2FA._tadsEnlazado = true;
            let usandoRecuperacion = false;
            enlace2FA.addEventListener('click', (evento) => {
                evento.preventDefault();
                usandoRecuperacion = !usandoRecuperacion;

                const bloqueCodigo = document.getElementById('bloqueCodigo');
                const bloqueRecuperacion = document.getElementById('bloqueRecuperacion');
                const campoCodigo = document.getElementById('codigo');
                const campoRecuperacion = document.getElementById('codigoRecuperacion');

                if (bloqueCodigo) bloqueCodigo.style.display = usandoRecuperacion ? 'none' : '';
                if (bloqueRecuperacion) bloqueRecuperacion.style.display = usandoRecuperacion ? '' : 'none';
                enlace2FA.textContent = usandoRecuperacion ? 'Usar un código de autenticación' : 'Usar un código de recuperación';
                if (usandoRecuperacion && campoRecuperacion) campoRecuperacion.focus();
                else if (!usandoRecuperacion && campoCodigo) campoCodigo.focus();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarAuth);
    } else {
        inicializarAuth();
    }
})();
