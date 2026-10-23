(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- errores de validación bajo cada campo ---------- */

    Tads.limpiarErrores = function (formulario) {
        formulario.querySelectorAll('.is-invalid').forEach((el) => { el.classList.remove('is-invalid'); el.removeAttribute('aria-invalid'); const errorId = el.dataset.errorId; if (errorId) { el.setAttribute('aria-describedby', (el.getAttribute('aria-describedby') || '').split(' ').filter(id => id !== errorId).join(' ')); delete el.dataset.errorId; } });
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

            if (!campo) { sueltos.push(mensajes[0]); return; }

            campo.classList.add('is-invalid');
            campo.setAttribute('aria-invalid', 'true');
            const referencia = campo.closest('.t-buscador') ?? campo.closest('.input-group') ?? campo;
            const aviso = document.createElement('div');
            aviso.className = 'invalid-feedback t-error d-block';
            aviso.id = `error-${campo.id || clave.replace(/[^a-z0-9]/gi, '-')}`;
            campo.dataset.errorId = aviso.id;
            campo.setAttribute('aria-describedby', `${campo.getAttribute('aria-describedby') || ''} ${aviso.id}`.trim());
            aviso.textContent = mensajes[0];
            referencia.after(aviso);
            primero ??= campo;
        });

        if (!Object.keys(errores).length && (json?.mensaje || json?.message)) sueltos.push(json.mensaje || json.message);

        const alerta = formulario.querySelector('.t-alerta-form');
        if (alerta) { alerta.setAttribute('role', 'alert'); alerta.setAttribute('aria-live', 'assertive'); }
        if (sueltos.length && alerta) {
            alerta.textContent = sueltos.join(' ');
            alerta.classList.add('visible');
        }

        primero?.focus();
    };


})(window);
