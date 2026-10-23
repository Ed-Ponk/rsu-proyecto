(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- MODELOS ---------- */
    Tads.Modulos.modelos = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'modelo',
                icono: 'bi-wrench-adjustable',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                columnas: [
                    { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'codigo', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                    { data: 'marca', render: (marca, tipo, fila) =>
                        `<span class="d-inline-flex align-items-center gap-2"><img class="t-logo-mini" style="width:34px;height:26px" src="${Tads.escapar(fila.logo)}" alt="">${Tads.escapar(marca)}</span>` },
                    { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'vehiculos', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: 'actualizado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(
                            Tads.botonEditar(fila.id),
                            Tads.botonEliminar(fila.id, fila.nombre, fila.vehiculos > 0 ? `No se puede eliminar: el modelo tiene ${fila.vehiculos} vehículo(s) asignado(s)` : ''),
                        ) },
                ],
            });
        },
    };


})(window);
