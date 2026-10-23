(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- MARCAS ---------- */
    function activarVistaPreviaDelLogo(cuerpo) {
        const caja = cuerpo.querySelector('#cajaLogo');
        const input = cuerpo.querySelector('#archivoLogo');
        if (caja && input) Tads.vistaPreviaArchivo(caja, input);
    }

    function motivoBloqueoMarca(marca) {
        if (marca.vehiculos > 0) return `No se puede eliminar: la marca tiene ${marca.vehiculos} vehículo(s) asignado(s)`;
        if (marca.modelos > 0) return `No se puede eliminar: la marca tiene ${marca.modelos} modelo(s) registrado(s)`;
        return '';
    }

    Tads.Modulos.marcas = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'marca',
                femenino: true,
                icono: 'bi-tags',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[1, 'asc']],
                alCargarFormulario: activarVistaPreviaDelLogo,
                columnas: [
                    { data: 'logo', orderable: false, searchable: false,
                        render: (url) => `<img class="t-logo-mini" src="${Tads.escapar(url)}" alt="Logo">` },
                    { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'modelos', render: (v) => Tads.insignia(v, v > 0 ? 'violeta' : 'gris') },
                    { data: 'vehiculos', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: 'actualizado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, fila.nombre, motivoBloqueoMarca(fila))) },
                ],
            });
        },
    };


})(window);
