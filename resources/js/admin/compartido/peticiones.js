(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
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
        search: 'Buscar:',
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
        if (estado === 409) return 'El registro cambió. Conserve sus datos y recargue el formulario antes de guardar.';
        if (estado === 413) return 'Los archivos superan el tamaño permitido por el servidor.';
        if (estado === 429) return 'Demasiadas solicitudes. Espere antes de volver a intentar.';
        if (estado === 502 || estado === 503) return 'El servicio no está disponible. Intente más tarde.';
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
            return { ok: false, estado: 0, json: { mensaje: 'Se perdió la conexión. Si estaba guardando, compruebe el listado antes de reintentar: la operación puede haberse completado.' }, texto: '' };
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


})(window);
