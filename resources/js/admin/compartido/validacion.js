(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
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


})(window);
