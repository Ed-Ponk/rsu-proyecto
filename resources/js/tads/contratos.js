/**
 * Módulo de Contratos (Tads)
 *
 * Administra el listado DataTables de contratos, validación dinámica de fechas según
 * el tipo de contrato (temporal vs permanente/nombrado) y cambio de estado
 * activar/desactivar mediante SweetAlert2 y petición PATCH asíncrona.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const colorDeEstado = { 'Vigente': 'verde', 'Vencido': 'rojo', 'Inactivo': 'gris', 'Por iniciar': 'azul' };
    const colorDeTipo = { temporal: 'ambar', nombrado: 'violeta', permanente: 'azul' };

    // La fecha de fin solo se llena en los contratos temporales.
    function activarFormularioDeContrato(cuerpo) {
        const tipo = cuerpo.querySelector('#contratoTipo');
        const inicio = cuerpo.querySelector('#contratoInicio');
        const fin = cuerpo.querySelector('#contratoFin');
        const ayuda = cuerpo.querySelector('#ayudaFin');
        if (!tipo || !inicio || !fin || !ayuda) return;

        function revisarTipo() {
            const esTemporal = tipo.value === 'temporal';
            fin.disabled = !esTemporal;
            fin.required = esTemporal;
            if (!esTemporal) fin.value = '';
            ayuda.textContent = esTemporal
                ? 'Obligatoria en los contratos temporales y posterior al inicio.'
                : 'No aplica: los contratos nombrados y permanentes no tienen fecha de fin.';
        }

        tipo.addEventListener('change', revisarTipo);
        inicio.addEventListener('change', () => { if (inicio.value) fin.min = inicio.value; });
        revisarTipo();
    }

    Tads.Modulos.contratos = {
        init: function (contenedor) {
            const urlListado = contenedor.dataset.urlListado;
            const urlRecurso = contenedor.dataset.urlRecurso;
            const urlCrear = contenedor.dataset.urlCrear;
            const campo = (id) => contenedor.querySelector(`#${id}`);

            const gestorContratos = new Tads.Gestor({
                entidad: 'contrato',
                icono: 'bi-file-earmark-text',
                urlListado: urlListado,
                urlRecurso: urlRecurso,
                urlCrear: urlCrear,
                orden: [],
                // El rango y el criterio se validan y aplican en el controlador.
                filtros: () => ({
                    criterio: campo('filtroCriterio')?.value || 'vigencia',
                    desde: campo('filtroDesde')?.value || '',
                    hasta: campo('filtroHasta')?.value || '',
                    tipo: campo('filtroTipo')?.value || '',
                }),
                alCargarFormulario: activarFormularioDeContrato,
                columnas: [
                    { data: 'dni', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                    { data: 'persona', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'tipo_texto', render: (texto, t, fila) => Tads.insignia(texto, colorDeTipo[fila.tipo] ?? 'gris') },
                    { data: 'inicio' },
                    { data: 'fin', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'salario', render: (v) => `<span class="t-codigo">S/ ${Tads.escapar(v)}</span>` },
                    { data: 'funcion', render: (v) => Tads.insignia(v, v === 'Conductor' ? 'azul' : 'turquesa') },
                    { data: 'estado', render: (v) => Tads.insignia(v, colorDeEstado[v] ?? 'gris') },
                    { data: null, orderable: false, searchable: false, className: 'text-center', render: (fila) => {
                        const cambiar = fila.activo
                            ? `<button type="button" class="btn t-boton-icono t-neutro" data-cambiar="${fila.id}" data-activo="1" data-bs-toggle="tooltip" title="Desactivar contrato"><i class="bi bi-toggle-on"></i></button>`
                            : `<button type="button" class="btn t-boton-icono t-exito" data-cambiar="${fila.id}" data-activo="0" data-bs-toggle="tooltip" title="Activar contrato"><i class="bi bi-toggle-off"></i></button>`;
                        return Tads.acciones(Tads.botonEditar(fila.id), cambiar);
                    } },
                ],
            });

            contenedor._gestor = gestorContratos;

            const fDesde = campo('filtroDesde');
            const fHasta = campo('filtroHasta');
            const fTipo = campo('filtroTipo');

            campo('botonFiltrar')?.addEventListener('click', () => gestorContratos.recargar());
            fTipo?.addEventListener('change', () => gestorContratos.recargar());

            // "Hasta" nunca puede quedar antes de "Desde".
            fDesde?.addEventListener('change', () => {
                fHasta.min = fDesde.value;
                if (fHasta.value && fHasta.value < fDesde.value) fHasta.value = fDesde.value;
            });

            [fDesde, fHasta].forEach((f) => f?.addEventListener('keyup', (e) => { if (e.key === 'Enter') gestorContratos.recargar(); }));

            campo('botonLimpiar')?.addEventListener('click', () => {
                campo('filtroCriterio').value = 'vigencia';
                fDesde.value = '';
                fHasta.value = '';
                fHasta.min = '';
                fTipo.value = '';
                gestorContratos.recargar();
            });

            const tablaEl = contenedor.querySelector('#tabla') || document.getElementById('tabla');
            if (tablaEl) {
                tablaEl.addEventListener('click', async (evento) => {
                    const boton = evento.target.closest('[data-cambiar]');
                    if (!boton) return;

                    const estabaActivo = boton.dataset.activo === '1';

                    const decision = await Swal.fire({
                        title: estabaActivo ? '¿Desactivar contrato?' : '¿Activar contrato?',
                        text: estabaActivo
                            ? 'La persona dejará de estar disponible para las programaciones.'
                            : 'La persona volverá a estar disponible para las programaciones.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: estabaActivo ? 'Sí, desactivar' : 'Sí, activar',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true,
                    });

                    if (!decision.isConfirmed) return;

                    const { ok, estado, json } = await Tads.peticion(`${urlRecurso}/${boton.dataset.cambiar}/estado`, {
                        metodo: 'POST',
                        cuerpo: Tads.cuerpoConMetodo('PATCH'),
                    });

                    if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo cambiar el estado');

                    gestorContratos.recargar();
                    Tads.aviso(json.mensaje);
                });
            }
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
