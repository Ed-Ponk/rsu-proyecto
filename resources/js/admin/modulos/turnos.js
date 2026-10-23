(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- TURNOS ---------- */
    Tads.Modulos.turnos = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'turno',
                icono: 'bi-clock-fill',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[2, 'asc']],
                alCargarListado: (json) => {
                    const aviso = document.getElementById('avisoHuecos');
                    if (!aviso) return;
                    aviso.hidden = !(json.huecos?.length);
                    document.getElementById('textoHuecos').textContent = (json.huecos ?? []).join(' · ');
                },
                columnas: [
                    { data: 'nombre', render: (v, t, fila) => `<strong>${Tads.escapar(v)}</strong>`
                        + (fila.nocturno ? ` ${Tads.insignia('Nocturno', 'violeta')}` : '') },
                    { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'entrada', render: (v) => Tads.insignia(v, 'verde') },
                    { data: 'salida', render: (v) => Tads.insignia(v, 'rojo') },
                    { data: 'asistencias', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: 'actualizado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(
                            Tads.botonEditar(fila.id),
                            Tads.botonEliminar(fila.id, fila.nombre, fila.asistencias > 0 ? `No se puede eliminar: el turno tiene ${fila.asistencias} asistencia(s) registrada(s)` : ''),
                        ) },
                ],
            });
        },
    };


})(window);
