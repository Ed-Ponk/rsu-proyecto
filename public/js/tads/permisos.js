/**
 * Módulo de permisos de usuario (Tads, Avance 05).
 *
 * Pantalla «Permisos de Usuario»: casilla general «Seleccionar todos», casilla «Todo» por módulo, contador de permisos
 * elegidos y guardado por AJAX. Las casillas bloqueadas (administradores o permisos que quien edita no posee) no cuentan.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    Tads.Modulos.permisos = {
        init: function (formulario) {
            const total = Number(formulario.dataset.total || 0);
            const casillas = () => [...formulario.querySelectorAll('input[name="permisos[]"]')];
            const editables = () => casillas().filter((c) => !c.disabled);
            const general = formulario.querySelector('#seleccionarTodos');
            const contador = formulario.querySelector('#contadorPermisos');
            const contadorBarra = formulario.querySelector('#contadorBarra');

            /** Estado de una casilla «Todo»: marcada si todas las editables lo están, intermedia si solo algunas. */
            function resumir(caja, lista) {
                const posibles = lista.filter((c) => !c.disabled);
                const marcadas = posibles.filter((c) => c.checked).length;
                caja.checked = posibles.length > 0 && marcadas === posibles.length;
                caja.indeterminate = marcadas > 0 && marcadas < posibles.length;
            }

            function refrescar() {
                formulario.querySelectorAll('.pm-permiso').forEach((etiqueta) => {
                    etiqueta.classList.toggle('pm-activo', etiqueta.querySelector('input').checked);
                });

                formulario.querySelectorAll('.pm-modulo').forEach((modulo) => {
                    resumir(modulo.querySelector('.pm-marcar-modulo'), [...modulo.querySelectorAll('input[name="permisos[]"]')]);
                });

                if (general) resumir(general, casillas());

                const elegidos = casillas().filter((c) => c.checked).length;
                const texto = `${elegidos} de ${total} permisos seleccionados`;
                if (contador) contador.textContent = `(${texto})`;
                if (contadorBarra) contadorBarra.textContent = texto;
            }

            general?.addEventListener('change', () => {
                editables().forEach((c) => { c.checked = general.checked; });
                refrescar();
            });

            formulario.querySelectorAll('.pm-marcar-modulo').forEach((caja) => {
                caja.addEventListener('change', () => {
                    caja.closest('.pm-modulo').querySelectorAll('input[name="permisos[]"]:not(:disabled)').forEach((c) => { c.checked = caja.checked; });
                    refrescar();
                });
            });

            formulario.addEventListener('change', (evento) => {
                if (evento.target.matches('input[name="permisos[]"]')) refrescar();
            });

            refrescar();

            formulario.addEventListener('submit', async (evento) => {
                evento.preventDefault();
                if (formulario.dataset.soloLectura === '1') return;

                const boton = formulario.querySelector('button[type="submit"]');
                boton.disabled = true;

                // Las casillas deshabilitadas no viajan en el formulario: el servidor conserva lo que el editor no puede tocar.
                const datos = new FormData(formulario);
                const { ok, estado, json } = await Tads.peticion(formulario.dataset.urlGuardar, { metodo: 'POST', cuerpo: datos });
                boton.disabled = false;

                if (!ok) return Tads.alertaDeError(estado, json, 'No se pudieron guardar los permisos');

                Tads.aviso(json?.mensaje ?? 'Permisos actualizados');
                setTimeout(() => { window.location.href = formulario.dataset.urlLista; }, 900);
            });
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
