(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- TIPOS DE VEHÍCULO ---------- */
    Tads.Modulos.tiposvehiculo = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'tipo de vehículo',
                icono: 'bi-truck-flatbed',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                columnas: [
                    { data: 'nombre', render: (v) => Tads.insignia(v, 'azul') },
                    { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'vehiculos', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: 'actualizado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(
                            Tads.botonEditar(fila.id),
                            Tads.botonEliminar(fila.id, fila.nombre, fila.vehiculos > 0 ? `No se puede eliminar: el tipo tiene ${fila.vehiculos} vehículo(s) asignado(s)` : ''),
                        ) },
                ],
            });
        },
    };


})(window);
