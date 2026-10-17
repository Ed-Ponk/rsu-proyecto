/**
 * Módulo de Colores (Tads)
 *
 * Administra el catálogo de colores vehiculares con sincronización bidireccional
 * entre el picker nativo <input type="color">, el campo hexadecimal y la vista previa visual.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    function activarSelectorDeColor(cuerpo) {
        const selector = cuerpo.querySelector('#selectorColor');
        const campoCodigo = cuerpo.querySelector('#colorCodigo');
        const vista = cuerpo.querySelector('#vistaColor');
        if (!selector || !campoCodigo || !vista) return;

        const esHexadecimal = (texto) => /^#[0-9A-Fa-f]{6}$/.test(texto);

        const pintar = (codigo) => {
            if (!esHexadecimal(codigo)) return;
            vista.style.backgroundColor = codigo;
            vista.textContent = codigo.toUpperCase();
        };

        selector.addEventListener('input', () => {
            campoCodigo.value = selector.value.toUpperCase();
            pintar(selector.value);
        });

        campoCodigo.addEventListener('input', () => {
            let texto = campoCodigo.value.trim();
            if (texto && texto[0] !== '#') texto = '#' + texto;
            if (esHexadecimal(texto)) {
                selector.value = texto;
                pintar(texto);
            }
        });

        pintar(campoCodigo.value);
    }

    Tads.Modulos.colores = {
        init: function (contenedor) {
            const urlListado = contenedor.dataset.urlListado;
            const urlRecurso = contenedor.dataset.urlRecurso;
            const urlCrear = contenedor.dataset.urlCrear;

            const gestorColores = new Tads.Gestor({
                entidad: 'color',
                icono: 'bi-palette',
                urlListado: urlListado,
                urlRecurso: urlRecurso,
                urlCrear: urlCrear,
                orden: [[1, 'asc']],
                alCargarFormulario: activarSelectorDeColor,
                columnas: [
                    { data: 'codigo', orderable: false, searchable: false,
                        render: (codigo) => `<span class="t-muestra-color" style="background-color:${Tads.escapar(codigo)}"></span>` },
                    { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'codigo', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                    { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'vehiculos', render: (v) => Tads.insignia(v, v > 0 ? 'azul' : 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: 'actualizado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(
                            Tads.botonEditar(fila.id),
                            Tads.botonEliminar(fila.id, fila.nombre, fila.vehiculos > 0 ? `No se puede eliminar: el color tiene ${fila.vehiculos} vehículo(s) asignado(s)` : ''),
                        ) },
                ],
            });

            contenedor._gestor = gestorColores;
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
