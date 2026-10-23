(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- piezas de HTML que se repiten en las columnas ---------- */

    Tads.insignia = (texto, color = 'gris') => `<span class="t-insignia t-${color}">${Tads.escapar(texto)}</span>`;

    /** Duración legible: 45 → "45 min", 60 → "1 h", 90 → "1 h 30 min", 120 → "2 h". */
    Tads.duracion = function (minutos) {
        const total = Math.max(0, Math.round(Number(minutos) || 0));
        if (total < 60) return `${total} min`;

        const horas = Math.floor(total / 60);
        const resto = total % 60;
        return resto ? `${horas} h ${resto} min` : `${horas} h`;
    };

    /** Etiqueta de puntualidad de una ENTRADA: tiempo de tardanza (lo calcula el backend) o "A tiempo". */
    Tads.insigniaTardanza = (minutos) => (minutos > 0
        ? Tads.insignia(`Tardanza ${Tads.duracion(minutos)}`, 'rojo')
        : Tads.insignia('A tiempo', 'verde'));

    /** "18/10/2026 09:30" → "2026-10-18 09:30": esa forma sí se ordena como texto igual que por calendario. */
    Tads.fechaIso = function (texto) {
        const partes = String(texto ?? '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}:\d{2}))?/);
        return partes ? `${partes[3]}-${partes[2]}-${partes[1]}${partes[4] ? ` ${partes[4]}` : ''}` : '';
    };

    /** Para columnas de fecha en texto plano: se muestra tal cual y se ordena por calendario. */
    Tads.ordenFecha = (valor, modo) => (modo === 'sort' || modo === 'type' ? Tads.fechaIso(valor) : (valor ?? ''));

    Tads.celdaFecha = function (valor, modo) {
        if (modo === 'sort' || modo === 'type') return Tads.fechaIso(valor);
        if (!valor) return '<span class="text-muted">—</span>';
        const [dia, hora] = String(valor).split(' ');
        return `<span class="t-fecha">${Tads.escapar(dia)}${hora ? `<small>${Tads.escapar(hora)}</small>` : ''}</span>`;
    };

    Tads.acciones = (...botones) => `<div class="t-acciones">${botones.join('')}</div>`;

    Tads.botonEditar = (id) =>
        `<button type="button" class="btn t-boton-icono t-editar" data-editar="${id}" data-bs-toggle="tooltip" title="Editar"><i class="bi bi-pencil-square"></i></button>`;

    /**
     * Botón que no se puede usar ahora. No lleva `disabled` (así sigue recibiendo foco): se marca con aria-disabled,
     * su nombre accesible es el motivo y el motivo también sale como ayuda al pasar el mouse o con el teclado.
     */
    Tads.botonBloqueado = (icono, clase, motivo) =>
        `<button type="button" class="btn t-boton-icono ${clase} t-bloqueado" aria-disabled="true" aria-label="${Tads.escapar(motivo)}" data-bs-toggle="tooltip" title="${Tads.escapar(motivo)}"><i class="bi ${icono}"></i></button>`;

    /** Si se pasa un motivo, el botón queda bloqueado y el motivo se ve como ayuda. */
    Tads.botonEliminar = function (id, nombre, motivoBloqueo = '') {
        if (motivoBloqueo) return Tads.botonBloqueado('bi-trash3-fill', 't-eliminar', motivoBloqueo);

        return `<button type="button" class="btn t-boton-icono t-eliminar" data-eliminar="${id}" data-nombre="${Tads.escapar(nombre ?? '')}" data-bs-toggle="tooltip" title="Eliminar"><i class="bi bi-trash3-fill"></i></button>`;
    };

    /** Vista previa de una imagen elegida en un <input type="file"> dentro de su caja punteada. */
    Tads.vistaPreviaArchivo = function (caja, input) {
        caja.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            const archivo = input.files?.[0];
            if (!archivo) return;
            const lector = new FileReader();
            lector.onload = (e) => {
                caja.innerHTML = `<div><img src="${e.target.result}" alt="Vista previa"><small>${Tads.escapar(archivo.name)}</small></div>`;
            };
            lector.readAsDataURL(archivo);
        });
    };


})(window);
