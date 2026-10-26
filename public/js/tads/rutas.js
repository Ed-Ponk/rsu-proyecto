/**
 * Módulos de rutas (Tads)
 *
 *   - Grupos de personal: plantillas (turno, zona, vehículo, días, conductor y ayudantes)
 *   - Programaciones: una fila por día de trabajo; individual (desde un grupo) o masiva (varios grupos).
 *     Antes de guardar hay que pulsar "Validar disponibilidad"; cualquier cambio posterior vuelve a bloquear el guardado.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const DIAS = { 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb', 7: 'Dom' };

    /* ------------------------------------------------------------------ ayudantes según la capacidad del vehículo */

    function ranuraDeAyudante(url, numero, inicial) {
        const id = `ayudante${Date.now().toString(36)}${numero}`;
        const datos = inicial ? ` data-inicial="${Tads.escapar(JSON.stringify(inicial))}"` : '';

        return `
            <div class="col-md-6" data-ranura>
                <label for="${id}" class="form-label ${numero === 1 ? 't-obligatorio' : ''}" data-etiqueta>Ayudante ${numero}</label>
                <div class="t-buscador" data-tads-buscador data-url="${Tads.escapar(url)}"
                    data-mensaje="Seleccione un ayudante de la lista." data-vacio="No hay ayudantes activos."
                    data-sin-coincidencias="No hay ayudantes que coincidan con «:texto»." data-cambiar="Cambiar ayudante"${datos}>
                    <input type="hidden" name="ayudantes[]" value="${inicial ? Tads.escapar(String(inicial.id)) : ''}">
                    <div class="t-buscador-entrada">
                        <i class="bi bi-search"></i>
                        <input type="search" id="${id}" class="form-control" ${numero === 1 ? 'required' : ''} autocomplete="off"
                            placeholder="Buscar ayudante por nombre o DNI" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="${id}Lista">
                    </div>
                    <ul class="t-buscador-lista" id="${id}Lista" role="listbox" hidden></ul>
                </div>
            </div>`;
    }

    /**
     * Deja exactamente `cantidad` ranuras de ayudante. Las que ya existen conservan su valor; las nuevas se crean
     * (con el ayudante precargado si viene en `items`). Con `reiniciar` se vacían y se arman de nuevo.
     */
    function ajustarAyudantes(contenedor, cantidad, items = [], reiniciar = false) {
        if (reiniciar) contenedor.innerHTML = '';

        const actuales = [...contenedor.querySelectorAll('[data-ranura]')];
        actuales.slice(cantidad).forEach((ranura) => ranura.remove());

        for (let i = actuales.length; i < cantidad; i++) {
            contenedor.insertAdjacentHTML('beforeend', ranuraDeAyudante(contenedor.dataset.url, i + 1, items[i] ?? null));
        }

        Tads.iniciarBuscadores(contenedor);
    }

    const capacidadDe = (raizVehiculo) => {
        if (raizVehiculo?._tadsItem?.capacidad) return Number(raizVehiculo._tadsItem.capacidad);
        try { return Number(JSON.parse(raizVehiculo?.dataset.inicial ?? 'null')?.capacidad) || null; } catch (e) { return null; }
    };

    /** Nº de ayudantes que caben: la capacidad menos el conductor (siempre se pide al menos uno). */
    const ayudantesQueCaben = (capacidad) => Math.max(1, (capacidad ?? 2) - 1);

    /* ------------------------------------------------------------------ grupos de personal */

    function activarFormularioDeGrupo(cuerpo) {
        const contenedor = cuerpo.querySelector('[data-ranuras-ayudantes]');
        const vehiculo = cuerpo.querySelector('[data-vehiculo]');
        const texto = cuerpo.querySelector('[data-capacidad-texto]');
        if (!contenedor) return;

        let iniciales = [];
        try { iniciales = JSON.parse(contenedor.dataset.iniciales || '[]'); } catch (e) { iniciales = []; }

        const actualizar = (primeraVez) => {
            const capacidad = capacidadDe(vehiculo);
            const cantidad = capacidad ? ayudantesQueCaben(capacidad) : Math.max(1, iniciales.length);

            if (texto) {
                texto.textContent = capacidad
                    ? `Capacidad: ${capacidad} persona(s) · 1 conductor y ${Math.max(0, capacidad - 1)} ayudante(s).`
                    : 'Elija un vehículo para saber cuántos ayudantes caben.';
                texto.classList.toggle('text-danger', capacidad === 1);
            }

            // Si se quitó el vehículo no se sabe la capacidad: se conservan los ayudantes ya elegidos.
            if (!capacidad && !primeraVez) return;

            ajustarAyudantes(contenedor, cantidad, primeraVez ? iniciales : []);
        };

        vehiculo?.addEventListener('change', () => actualizar(false));
        actualizar(true);

        cuerpo.querySelectorAll('[data-dias]').forEach((enlace) => enlace.addEventListener('click', (evento) => {
            evento.preventDefault();
            const dias = enlace.dataset.dias.split(',').filter(Boolean);
            cuerpo.querySelectorAll('input[name="weekdays[]"]').forEach((caja) => { caja.checked = dias.includes(caja.value); });
        }));
    }

    Tads.Modulos.grupos = {
        init: function (contenedor) {
            contenedor._gestor = new Tads.Gestor({
                entidad: 'grupo de personal',
                icono: 'bi-people-fill',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso: contenedor.dataset.urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                alCargarFormulario: activarFormularioDeGrupo,
                columnas: [
                    { data: 'nombre', render: (v, modo, fila) => modo === 'display'
                        ? `<strong>${Tads.escapar(v)}</strong>${fila.alertas?.length ? ` <i class="bi bi-exclamation-triangle-fill text-warning" data-bs-toggle="tooltip" data-bs-html="false" title="${Tads.escapar(fila.alertas.join(' · '))}"></i>` : ''}`
                        : v },
                    { data: 'turno', render: (v) => Tads.insignia(v ?? '—', 'ambar') },
                    { data: 'zona', defaultContent: '' },
                    { data: 'vehiculo', render: (v) => v ? `<span class="t-codigo">${Tads.escapar(v)}</span>` : '—' },
                    { data: 'conductor', defaultContent: '—' },
                    { data: 'ayudantes', orderable: false, render: (lista, modo) => modo === 'display'
                        ? `<div class="t-pila">${lista.map((n) => `<span>${Tads.escapar(n)}</span>`).join('')}</div>`
                        : lista.join(', ') },
                    { data: 'dias', orderable: false, render: (dias, modo) => modo === 'display'
                        ? dias.map((d) => Tads.insignia(DIAS[d], 'azul')).join(' ')
                        : dias.map((d) => DIAS[d]).join(' ') },
                    { data: 'activo', render: (v) => v ? Tads.insignia('Activo', 'verde') : Tads.insignia('Inactivo', 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(Tads.botonEditar(fila.id), Tads.botonEliminar(fila.id, fila.nombre)) },
                ],
            });
        },
    };

    /* ------------------------------------------------------------------ validación de disponibilidad */

    const lista = (textos) => `<ul>${textos.map((t) => `<li>${Tads.escapar(t)}</li>`).join('')}</ul>`;

    function pintarResultado(panel, resultado, esReemplazo = false) {
        const avisos = resultado.avisos?.length
            ? `<div class="t-validacion t-validacion-info mt-2"><strong><i class="bi bi-info-circle-fill me-1"></i> Información</strong>${lista(resultado.avisos)}</div>`
            : '';

        if (resultado.ok) {
            const total = resultado.total;
            panel.innerHTML = `
                <div class="t-validacion t-validacion-ok">
                    <strong><i class="bi bi-check-circle-fill me-1"></i> Todo está correcto. Puede guardar la programación.</strong>
                    <div class="small">${esReemplazo
                        ? (total === 1 ? 'Se reemplazará en 1 programación' : `Se reemplazará en ${total} programaciones`)
                        : (total === 1 ? 'Se generará 1 programación' : `Se generarán ${total} programaciones`)}.</div>
                </div>${avisos}`;
            return;
        }

        const sugerencias = resultado.sugerencias?.length
            ? `<strong class="d-block mt-2"><i class="bi bi-lightbulb-fill me-1"></i> Sugerencias:</strong>${lista(resultado.sugerencias)}`
            : '';

        panel.innerHTML = `
            <div class="t-validacion t-validacion-error" role="alert">
                <strong><i class="bi bi-exclamation-triangle-fill me-1"></i> Hay errores que corregir</strong>
                ${lista(resultado.errores)}${sugerencias}
            </div>${avisos}`;
    }

    /** Conecta "Validar disponibilidad" con el botón de guardar: solo se activa tras una validación correcta. */
    function activarValidacion(cuerpo) {
        const formulario = cuerpo.querySelector('form[data-programacion-formulario]');
        const botonValidar = formulario.querySelector('[data-validar]');
        const botonGuardar = formulario.querySelector('[data-guardar]');
        const panel = formulario.querySelector('[data-resultado]');
        let hayResultado = false;

        const invalidar = () => {
            botonGuardar.disabled = true;
            if (!hayResultado) return;
            hayResultado = false;
            panel.innerHTML = '<div class="t-validacion t-validacion-info"><i class="bi bi-info-circle-fill me-1"></i> Los datos han cambiado. Valide la disponibilidad nuevamente.</div>';
        };

        formulario.addEventListener('input', invalidar);
        formulario.addEventListener('change', invalidar);

        botonValidar.addEventListener('click', async () => {
            Tads.limpiarErrores(formulario);
            if (!formulario.checkValidity()) return formulario.reportValidity();

            const textoOriginal = botonValidar.innerHTML;
            botonValidar.disabled = true;
            botonValidar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Validando...';

            const datos = new FormData(formulario);
            datos.delete('_method');   // la validación siempre es un POST, aunque el formulario guarde con PUT
            const { ok, estado, json } = await Tads.peticion(formulario.dataset.urlValidar, { metodo: 'POST', cuerpo: datos });

            botonValidar.disabled = false;
            botonValidar.innerHTML = textoOriginal;

            if (!ok) {
                if (estado === 422) return Tads.mostrarErrores(formulario, json);
                return Tads.alertaDeError(estado, json, 'No se pudo validar la disponibilidad');
            }

            pintarResultado(panel, json, formulario.hasAttribute('data-reemplazo'));
            hayResultado = true;
            botonGuardar.disabled = !json.ok;
            panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    }

    /* ------------------------------------------------------------------ formularios de programación */

    function activarFormularioDeProgramacion(cuerpo) {
        const formulario = cuerpo.querySelector('form[data-programacion-formulario]');
        if (!formulario) return;

        activarValidacion(cuerpo);

        if (formulario.hasAttribute('data-masiva')) return activarProgramacionMasiva(formulario);
        if (formulario.hasAttribute('data-reemplazo')) return activarReemplazo(formulario);

        const contenedor = formulario.querySelector('[data-ranuras-ayudantes]');
        const grupo = formulario.querySelector('[data-grupo]');
        const vehiculo = formulario.querySelector('[data-vehiculo]');
        const conductor = formulario.querySelector('[data-conductor]');

        // Modificar una programación: los ayudantes que caben dependen del vehículo elegido.
        if (!grupo) {
            let iniciales = [];
            try { iniciales = JSON.parse(contenedor.dataset.iniciales || '[]'); } catch (e) { iniciales = []; }

            ajustarAyudantes(contenedor, Math.max(ayudantesQueCaben(capacidadDe(vehiculo)), 1), iniciales);
            vehiculo?.addEventListener('change', () => ajustarAyudantes(contenedor, ayudantesQueCaben(capacidadDe(vehiculo))));
            return;
        }

        // Nueva programación: al elegir el grupo se traen su conductor, ayudantes y días.
        const datos = formulario.querySelector('[data-datos-grupo]');
        const resumen = formulario.querySelector('[data-resumen-grupo]');

        grupo.addEventListener('change', async () => {
            const elegido = grupo._tadsItem;
            datos.hidden = !elegido;
            if (!elegido) return;

            const { ok, estado, json } = await Tads.peticion(`${formulario.dataset.urlPlantilla}/${elegido.id}/plantilla`);
            if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo cargar el grupo');

            resumen.innerHTML = [
                ['Grupo', json.nombre], ['Zona', json.zona.titulo], ['Turno', `${json.turno.titulo} (${json.turno.detalle})`], ['Vehículo', json.vehiculo.titulo],
            ].map(([etiqueta, valor]) => `<div><small>${etiqueta}</small><strong>${Tads.escapar(valor)}</strong></div>`).join('');

            conductor.tadsElegir(json.conductor);
            ajustarAyudantes(contenedor, Math.max(ayudantesQueCaben(json.vehiculo.capacidad), json.ayudantes.length), json.ayudantes, true);
            formulario.querySelectorAll('input[name="dias[]"]').forEach((caja) => { caja.checked = json.dias.includes(Number(caja.value)); });
        });

        // El fin del rango nunca puede quedar antes del inicio.
        const inicio = formulario.querySelector('[name="inicio"]');
        const fin = formulario.querySelector('[name="fin"]');
        inicio.addEventListener('change', () => { fin.min = inicio.value || fin.min; if (fin.value && fin.value < inicio.value) fin.value = inicio.value; });
    }

    /** Reemplazo masivo: según lo elegido (conductor, ayudante o vehículo) se activa solo su par de buscadores. */
    function activarReemplazo(formulario) {
        const pares = [...formulario.querySelectorAll('[data-par]')];

        const mostrar = () => {
            const rol = formulario.querySelector('input[name="rol"]:checked').value;
            pares.forEach((par) => {
                const activo = par.dataset.par === rol;
                par.hidden = !activo;
                par.querySelectorAll('input').forEach((campo) => { campo.disabled = !activo; });
            });
        };

        formulario.querySelectorAll('input[name="rol"]').forEach((radio) => radio.addEventListener('change', mostrar));
        mostrar();
    }

    function activarProgramacionMasiva(formulario) {
        const opciones = [...formulario.querySelectorAll('[data-grupo-opcion]')];
        const cajas = opciones.map((o) => o.querySelector('input'));
        const contador = formulario.querySelector('[data-contador-grupos]');
        const todos = formulario.querySelector('[data-todos-grupos]');
        const filtro = formulario.querySelector('[data-filtro-grupos]');

        const actualizar = () => {
            const visibles = cajas.filter((c) => !c.closest('[data-grupo-opcion]').hidden);
            contador.textContent = cajas.filter((c) => c.checked).length;
            todos.checked = visibles.length > 0 && visibles.every((c) => c.checked);
        };

        todos.addEventListener('change', () => {
            cajas.filter((c) => !c.closest('[data-grupo-opcion]').hidden).forEach((c) => { c.checked = todos.checked; });
            actualizar();
            formulario.dispatchEvent(new Event('change'));   // invalida una validación anterior
        });
        formulario.addEventListener('change', (e) => { if (e.target.closest('[data-grupo-opcion]')) actualizar(); });

        filtro.addEventListener('input', () => {
            const texto = filtro.value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            opciones.forEach((o) => { o.hidden = texto !== '' && !o.dataset.texto.includes(texto); });
            actualizar();
        });
        // Escribir en el filtro no debe contar como un cambio de datos.
        filtro.addEventListener('input', (e) => e.stopPropagation(), true);
        actualizar();
    }

    /* ------------------------------------------------------------------ listado de programaciones */

    const textoPlano = (valor, modo, formato) => (modo === 'display' ? formato(valor) : String(valor ?? ''));

    Tads.Modulos.programaciones = {
        init: function (contenedor) {
            const urlRecurso = contenedor.dataset.urlRecurso;
            const campo = (id) => document.getElementById(id);
            const valoresIniciales = { desde: campo('filtroDesde').value, hasta: campo('filtroHasta').value };

            const gestor = new Tads.Gestor({
                entidad: 'programación',
                femenino: true,
                icono: 'bi-clock-history',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                porPagina: 15,
                filtros: () => ({
                    desde: campo('filtroDesde').value, hasta: campo('filtroHasta').value,
                    zona_id: campo('filtroZona').value, turno_id: campo('filtroTurno').value,
                }),
                alCargarListado: (json) => {
                    campo('avisoTruncado').hidden = !json.truncado;
                    // El botón de quitar solo aparece si el listado muestra programaciones sobre un feriado.
                    campo('botonQuitarFeriados').hidden = !json.data.some((f) => f.feriado && f.estado === 'programado');
                },
                alCargarFormulario: activarFormularioDeProgramacion,
                exportar: { contenedor: '#botonesExportar', titulo: 'Programaciones', columnas: [0, 1, 2, 3, 4, 5, 6, 7] },
                columnas: [
                    { data: 'fecha', className: 'text-nowrap', render: Tads.ordenFecha },
                    { data: 'estado', render: (v, modo, fila) => {
                        if (modo !== 'display') return fila.estado_texto + (fila.feriado ? ` (feriado: ${fila.feriado})` : '') + (fila.sin_asistencia?.length ? ' (sin asistencia)' : '');

                        return Tads.insignia(fila.estado_texto, v === 'finalizado' ? 'verde' : 'azul')
                            + (fila.feriado ? `<div class="mt-1">${Tads.insignia(`Feriado: ${fila.feriado}`, 'rojo')}</div>` : '')
                            + (fila.sin_asistencia?.length
                                ? `<div class="mt-1"><span class="t-insignia t-ambar" data-bs-toggle="tooltip" title="Sin asistencia registrada: ${Tads.escapar(fila.sin_asistencia.join(', '))}"><i class="bi bi-exclamation-triangle-fill me-1"></i>Sin asistencia (${fila.sin_asistencia.length})</span></div>`
                                : '');
                    } },
                    { data: 'zona', defaultContent: '' },
                    { data: 'turno', render: (v, modo) => textoPlano(v, modo, (t) => Tads.insignia(t ?? '—', 'ambar')) },
                    { data: 'vehiculo', render: (v, modo, fila) => modo === 'display'
                        ? `<strong>${Tads.escapar(v ?? '')}</strong><br><span class="t-codigo">${Tads.escapar(fila.placa ?? '')}</span>`
                        : `${v ?? ''} (${fila.placa ?? ''})` },
                    { data: 'conductor', defaultContent: '—' },
                    { data: 'ayudantes', orderable: false, render: (nombres, modo) => modo === 'display'
                        ? `<div class="t-pila">${nombres.map((n) => `<span>${Tads.escapar(n)}</span>`).join('')}</div>`
                        : nombres.join(', ') },
                    { data: 'notas', defaultContent: '', render: (v, modo) => modo === 'display'
                        ? (v ? Tads.escapar(v) : '<span class="text-muted">—</span>') : (v ?? '') },
                    { data: null, orderable: false, searchable: false, className: 'text-center text-nowrap', render: (fila) => acciones(fila) },
                ],
            });

            contenedor._gestor = gestor;

            function acciones(fila) {
                const quien = `${fila.fecha} · ${fila.zona ?? ''} · ${fila.turno ?? ''}`;

                if (fila.estado === 'finalizado') {
                    return `<button type="button" class="btn t-boton btn-sm" data-historial="${fila.id}" data-titulo="${Tads.escapar(quien)}"><i class="bi bi-eye-fill me-1"></i> Ver detalle</button>`;
                }

                const finalizar = fila.puede_finalizar
                    ? `<button type="button" class="btn t-boton-icono t-exito" data-finalizar="${fila.id}" data-nombre="${Tads.escapar(quien)}" data-sin-asistencia="${Tads.escapar((fila.sin_asistencia ?? []).join(', '))}" data-bs-toggle="tooltip" title="Finalizar programación"><i class="bi bi-check-circle-fill"></i></button>`
                    : Tads.botonBloqueado('bi-check-circle-fill', 't-exito', 'Solo se puede finalizar desde el día programado');

                return Tads.acciones(
                    fila.puede_modificar ? Tads.botonEditar(fila.id) : Tads.botonBloqueado('bi-pencil-square', 't-editar', 'Ya pasó su fecha: solo se puede finalizar o eliminar'),
                    Tads.botonEliminar(fila.id, quien),
                    `<button type="button" class="btn t-boton-icono t-info" data-historial="${fila.id}" data-titulo="${Tads.escapar(quien)}" data-bs-toggle="tooltip" title="Ver historial de cambios"><i class="bi bi-clock-history"></i></button>`,
                    finalizar,
                );
            }

            document.getElementById('tabla').addEventListener('click', async (evento) => {
                const historial = evento.target.closest('[data-historial]');
                const finalizar = evento.target.closest('[data-finalizar]');

                if (historial) {
                    bootstrap.Tooltip.getInstance(historial)?.hide();
                    return gestor.abrirFormulario(
                        `${urlRecurso}/${historial.dataset.historial}/historial`,
                        `<i class="bi bi-clock-history me-2"></i>Programación del ${Tads.escapar(historial.dataset.titulo)}`,
                    );
                }

                if (!finalizar) return;

                const decision = await Swal.fire({
                    title: '¿Finalizar programación?',
                    html: `Se marcará como cumplida: <strong>${Tads.escapar(finalizar.dataset.nombre)}</strong>. Ya no podrá modificarse ni eliminarse.`
                        + (finalizar.dataset.sinAsistencia ? `<div class="alert alert-warning small mt-3 mb-0 text-start"><i class="bi bi-exclamation-triangle-fill me-1"></i> No registraron asistencia: <strong>${Tads.escapar(finalizar.dataset.sinAsistencia)}</strong>.</div>` : ''),
                    icon: 'question', showCancelButton: true, confirmButtonColor: '#16a34a',
                    confirmButtonText: 'Sí, finalizar', cancelButtonText: 'Cancelar', reverseButtons: true,
                });
                if (!decision.isConfirmed) return;

                const { ok, estado, json } = await Tads.peticion(`${urlRecurso}/${finalizar.dataset.finalizar}/finalizar`, {
                    metodo: 'POST', cuerpo: Tads.cuerpoConMetodo('PATCH'),
                });
                if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo finalizar la programación');

                gestor.recargar();
                Tads.aviso(json.mensaje);
            });

            campo('botonMasiva').addEventListener('click', () => {
                gestor.abrirFormulario(contenedor.dataset.urlMasiva, '<i class="bi bi-calendar2-range-fill me-2"></i>Programación masiva');
            });

            campo('botonReemplazo').addEventListener('click', () => {
                gestor.abrirFormulario(contenedor.dataset.urlReemplazo, '<i class="bi bi-arrow-left-right me-2"></i>Reemplazar conductor, ayudante o vehículo');
            });

            campo('botonQuitarFeriados').addEventListener('click', async () => {
                const decision = await Swal.fire({
                    title: '¿Quitar las programaciones en feriados?',
                    text: 'Se eliminarán las programaciones pendientes del rango filtrado que caen en un feriado activo. No se puede deshacer (queda en el historial).',
                    icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, quitar', cancelButtonText: 'Cancelar', reverseButtons: true,
                });
                if (!decision.isConfirmed) return;

                const cuerpo = new FormData();
                cuerpo.append('desde', campo('filtroDesde').value);
                cuerpo.append('hasta', campo('filtroHasta').value);
                const { ok, estado, json } = await Tads.peticion(contenedor.dataset.urlQuitarFeriados, { metodo: 'POST', cuerpo });
                if (!ok) return Tads.alertaDeError(estado, json, 'No se pudieron quitar las programaciones');

                gestor.recargar();
                Tads.aviso(json.mensaje);
            });

            campo('botonFiltrar').addEventListener('click', () => gestor.recargar());
            campo('botonLimpiar').addEventListener('click', () => {
                campo('filtroDesde').value = valoresIniciales.desde;
                campo('filtroHasta').value = valoresIniciales.hasta;
                campo('filtroZona').value = '';
                campo('filtroTurno').value = '';
                gestor.recargar();
            });
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
