(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- BITÁCORA (solo lectura) ---------- */
    Tads.Modulos.bitacora = {
        init: function (contenedor) {
            const campo = (id) => document.getElementById(id);
            const colores = { creado: 'verde', modificado: 'ambar', eliminado: 'rojo' };

            const tabla = $('#tabla').DataTable({
                serverSide: true,
                searching: false,
                ordering: false,
                ajax: async (_parametros, devolver) => {
                    const filtros = new URLSearchParams({ draw: _parametros.draw, start: _parametros.start, length: _parametros.length, modelo: campo('filtroModelo').value, accion: campo('filtroAccion').value });
                    const { ok, estado, json } = await Tads.peticion(`${contenedor.dataset.urlListado}?${filtros}`);
                    if (!ok) { Tads.alertaDeError(estado, json, 'No se pudo cargar la bitácora'); return devolver({ data: [] }); }
                    devolver(json);
                },
                columns: [
                    { data: 'fecha', render: Tads.celdaFecha },
                    { data: 'usuario', render: Tads.escapar },
                    { data: 'accion', render: (v) => Tads.insignia(v.charAt(0).toUpperCase() + v.slice(1), colores[v] ?? 'gris') },
                    { data: 'registro', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'detalle', render: (v) => `<small class="text-muted">${Tads.escapar(v)}</small>` },
                ],
                order: [[0, 'desc']],
                pageLength: 10,
                lengthChange: false,
                autoWidth: false,
                processing: true,
                language: Tads.idioma,
            });

            campo('botonFiltrar').addEventListener('click', () => tabla.ajax.reload());
            contenedor._tabla = tabla;
        },
    };


})(window);
