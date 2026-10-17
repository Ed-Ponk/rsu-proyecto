/**
 * Módulos de Catálogos Auxiliares (Tads)
 *
 * Administra los catálogos del panel:
 *   - Marcas (con vista previa de logotipo y bloqueo de borrado si tiene vehículos o modelos)
 *   - Tipos de Personal (con protección de funciones de sistema y bloqueo si tiene personal)
 *   - Modelos (con insignia de marca y bloqueo si tiene vehículos)
 *   - Tipos de Vehículo (clasificación de flota y bloqueo si tiene vehículos)
 */
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
                        + (fila.del_sistema ? ' <i class="bi bi-lock-fill text-muted" data-bs-toggle="tooltip" title="Función predefinida del sistema"></i>' : '') },
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

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
