(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- TIPOS DE PERSONAL ---------- */
    function motivoBloqueoTipoPersonal(tipo) {
        if (tipo.del_sistema) return 'No se puede eliminar: es una función predefinida del sistema';
        if (tipo.personal > 0) return `No se puede eliminar: el tipo tiene ${tipo.personal} persona(s) asignada(s)`;
        return '';
    }

    Tads.Modulos.tipospersonal = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'tipo de personal',
                icono: 'bi-person-badge',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [],
                columnas: [
                    { data: 'nombre', render: (v, t, fila) => Tads.insignia(v, 'azul')
                        + (fila.del_sistema ? ` ${Tads.insignia('Predefinido', 'gris')}` : '') },
                    { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'personal', render: (v) => Tads.insignia(v, v > 0 ? 'verde' : 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: 'actualizado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, fila.nombre, motivoBloqueoTipoPersonal(fila))) },
                ],
            });
        },
    };


})(window);
