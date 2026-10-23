(function (global) {
    'use strict';
    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};
    /* ---------- FERIADOS ---------- */
    function activarDiaDeLaSemana(cuerpo) {
        const fecha = cuerpo.querySelector('#feriadoFecha');
        const dia = cuerpo.querySelector('#feriadoDia');
        if (!fecha || !dia) return;

        const mostrar = () => {
            if (!fecha.value) { dia.textContent = 'Seleccione una fecha'; return; }
            // Se arma con hora fija para que la zona horaria del navegador no cambie el día.
            const nombre = new Date(`${fecha.value}T12:00:00`).toLocaleDateString('es-PE', { weekday: 'long' });
            dia.textContent = nombre.charAt(0).toUpperCase() + nombre.slice(1);
        };

        fecha.addEventListener('input', mostrar);
        mostrar();
    }

    Tads.Modulos.feriados = {
        init: function (contenedor) {
            const campo = (id) => document.getElementById(id);

            // Las tarjetas de arriba se actualizan con cada recarga del listado.
            function pintarResumen(resumen) {
                if (!resumen) return;
                Object.entries(resumen).forEach(([clave, valor]) => {
                    const cifra = contenedor.querySelector(`[data-kpi="${clave}"] .fw-bold`);
                    if (cifra) cifra.textContent = String(valor).trim();
                });
            }

            const gestor = new Tads.Gestor({
                entidad: 'feriado',
                icono: 'bi-flag-fill',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                filtros: () => ({
                    desde: campo('filtroDesde')?.value || '',
                    hasta: campo('filtroHasta')?.value || '',
                    estado: campo('filtroEstado')?.value || '',
                }),
                alCargarFormulario: activarDiaDeLaSemana,
                alCargarListado: (json) => pintarResumen(json.resumen),
                columnas: [
                    { data: 'orden', render: (valor, modo, fila) => modo === 'display'
                        ? `<span class="t-codigo">${Tads.escapar(fila.fecha)}</span>` : valor },
                    { data: 'descripcion', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'activo', render: (v) => v ? Tads.insignia('Activo', 'verde') : Tads.insignia('Inactivo', 'gris') },
                    { data: 'dia', render: (v) => Tads.insignia(v, 'azul') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, `${fila.fecha} · ${fila.descripcion}`)) },
                ],
            });

            contenedor._gestor = gestor;

            campo('botonFiltrar')?.addEventListener('click', () => gestor.recargar());
            campo('botonLimpiar')?.addEventListener('click', () => {
                ['filtroDesde', 'filtroHasta', 'filtroEstado'].forEach((id) => { campo(id).value = ''; });
                gestor.recargar();
            });

            const desde = campo('filtroDesde');
            const hasta = campo('filtroHasta');
            desde?.addEventListener('change', () => {
                hasta.min = desde.value;
                if (hasta.value && hasta.value < desde.value) hasta.value = desde.value;
            });

            /* ---------- Cargar Feriados Perú (con barra de progreso) ---------- */
            const botonCargar = campo('botonCargarFeriados');
            const TAMANO_LOTE = 4;
            const pausa = (ms) => new Promise((resolver) => setTimeout(resolver, ms));

            botonCargar?.addEventListener('click', async () => {
                const decision = await Swal.fire({
                    title: '¿Cargar feriados de Perú?',
                    text: `Se consultará la API pública Nager.Date y se cargarán los feriados oficiales de ${contenedor.dataset.anio}. Los que ya existan no se duplican.`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, cargar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                });
                if (!decision.isConfirmed) return;

                botonCargar.disabled = true;
                try {
                    await cargarFeriados();
                } finally {
                    botonCargar.disabled = false;
                }
            });

            async function cargarFeriados() {
                Swal.fire({
                    title: 'Cargando feriados de Perú',
                    html: `<div class="text-start">
                        <div class="small mb-2" id="cargaFuente"><i class="bi bi-cloud-arrow-down-fill me-1"></i>Fuente: <strong>API pública Nager.Date (date.nager.at)</strong></div>
                        <div class="progress" style="height:24px"><div id="cargaBarra" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width:0%">0%</div></div>
                        <div class="small text-muted mt-2" id="cargaEstado">Conectando con la API...</div>
                    </div>`,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                });

                const barra = () => document.getElementById('cargaBarra');
                const fijar = (porcentaje, texto) => {
                    const p = Math.min(100, Math.round(porcentaje));
                    if (barra()) { barra().style.width = `${p}%`; barra().textContent = `${p}%`; }
                    if (texto && document.getElementById('cargaEstado')) document.getElementById('cargaEstado').textContent = texto;
                };

                // Mientras la API responde la barra avanza despacio hasta el 40 %.
                let falso = 3;
                const espera = setInterval(() => { falso = Math.min(40, falso + 2); fijar(falso); }, 250);
                const consulta = await Tads.peticion(contenedor.dataset.urlConsultar);
                clearInterval(espera);

                if (!consulta.ok) {
                    Swal.close();
                    return Tads.alertaDeError(consulta.estado, consulta.json, 'No se pudieron consultar los feriados');
                }

                const { fuente, origen, feriados, anio } = consulta.json;
                const icono = origen === 'api' ? 'bi-cloud-check-fill text-success' : 'bi-hdd-fill text-warning';
                document.getElementById('cargaFuente').innerHTML = `<i class="bi ${icono} me-1"></i>Fuente: <strong>${Tads.escapar(fuente)}</strong>`
                    + (origen === 'api' ? '' : '<div class="text-warning-emphasis">No se pudo conectar con la API: se usa el calendario del sistema.</div>');
                fijar(45, `Se recibieron ${feriados.length} feriados de ${anio}. Guardando...`);

                let creados = 0;
                let existentes = 0;

                for (let i = 0; i < feriados.length; i += TAMANO_LOTE) {
                    const lote = feriados.slice(i, i + TAMANO_LOTE);
                    const cuerpo = new FormData();
                    lote.forEach((f, k) => {
                        cuerpo.append(`feriados[${k}][date]`, f.date);
                        cuerpo.append(`feriados[${k}][description]`, f.description);
                    });

                    const { ok, estado, json } = await Tads.peticion(contenedor.dataset.urlCargar, { metodo: 'POST', cuerpo });
                    if (!ok) {
                        Swal.close();
                        gestor.recargar();
                        return Tads.alertaDeError(estado, json, 'No se pudieron guardar los feriados');
                    }

                    creados += json.creados;
                    existentes += json.existentes;
                    const hechos = Math.min(i + TAMANO_LOTE, feriados.length);
                    fijar(45 + (55 * hechos) / feriados.length, `Guardando feriados: ${hechos} de ${feriados.length}`);
                    pintarResumen(json.resumen);
                    await pausa(180);   // se nota el avance aunque sean pocos registros
                }

                fijar(100, 'Carga completada');
                barra()?.classList.remove('progress-bar-animated');
                await pausa(500);

                gestor.recargar();
                Swal.fire({
                    icon: creados > 0 ? 'success' : 'info',
                    title: creados > 0 ? 'Feriados cargados' : 'Sin cambios',
                    html: (creados > 0
                        ? `Se cargaron <strong>${creados}</strong> feriado(s) de ${anio}${existentes ? `; ${existentes} ya existían` : ''}.`
                        : `Los feriados de ${anio} ya estaban cargados.`)
                        + `<div class="small text-muted mt-2">Fuente: ${Tads.escapar(fuente)}</div>`,
                    confirmButtonText: 'Entendido',
                });
            }
        },
    };


})(window);
