(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- USUARIOS DEL SISTEMA ---------- */
    Tads.Modulos.usuarios = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'usuario',
                icono: 'bi-person-gear',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                columnas: [
                    { data: 'nombre', render: (v, t, fila) => `<strong>${Tads.escapar(v)}</strong>${fila.actual ? ` ${Tads.insignia('Usted', 'azul')}` : ''}` },
                    { data: 'correo', render: Tads.escapar },
                    { data: 'activo', render: (v) => v ? Tads.insignia('Activo', 'verde') : Tads.insignia('Inactivo', 'gris') },
                    { data: 'dos_pasos', render: (v) => v ? Tads.insignia('Activada', 'verde') : Tads.insignia('No activada', 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(
                            Tads.botonEditar(fila.id),
                            Tads.botonEliminar(fila.id, fila.correo, fila.actual ? 'No puede eliminar su propia cuenta desde aquí' : ''),
                        ) },
                ],
            });
        },
    };


})(window);
