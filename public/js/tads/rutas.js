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

    function ranuraDeAyudante(url, numero, inicial, nombre = 'ayudantes[]') {
        const id = `ayudante${Date.now().toString(36)}${numero}`;
        const datos = inicial ? ` data-inicial="${Tads.escapar(JSON.stringify(inicial))}"` : '';

        return `
            <div class="col-md-6" data-ranura>
                <label for="${id}" class="form-label ${numero === 1 ? 't-obligatorio' : ''}" data-etiqueta>Ayudante ${numero}</label>
                <div class="t-buscador" data-tads-buscador data-url="${Tads.escapar(url)}"
                    data-mensaje="Seleccione un ayudante de la lista." data-vacio="No hay ayudantes activos."
                    data-sin-coincidencias="No hay ayudantes que coincidan con «:texto»." data-cambiar="Cambiar ayudante"${datos}>
                    <input type="hidden" name="${Tads.escapar(nombre)}" value="${inicial ? Tads.escapar(String(inicial.id)) : ''}">
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
            contenedor.insertAdjacentHTML('beforeend', ranuraDeAyudante(contenedor.dataset.url, i + 1, items[i] ?? null, contenedor.dataset.nombre ?? 'ayudantes[]'));
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

    const ICONOS = {
        vacaciones: 'bi-airplane-fill', contrato: 'bi-file-earmark-x-fill', ocupado: 'bi-calendar2-x-fill', lote: 'bi-people-fill', cruce: 'bi-arrow-left-right',
        inactivo: 'bi-person-slash', rol: 'bi-person-exclamation', duplicado: 'bi-person-x-fill', vehiculo: 'bi-truck', vehiculo_estado: 'bi-truck',
        capacidad: 'bi-people-fill', grupo: 'bi-collection-fill', fechas: 'bi-calendar-event-fill', dias: 'bi-calendar-week-fill', recurso: 'bi-geo-alt-fill',
        limite: 'bi-exclamation-octagon-fill', asistencia: 'bi-clipboard-check-fill', reemplazo: 'bi-arrow-left-right',
    };
    const POR_PANTALLA = 4;   // problemas visibles antes de «Ver N más»

    /** "02/12" (o "02/12/2027" si no es del año actual): corto para no cansar. */
    const fechaCorta = (iso) => {
        const [a, m, d] = iso.split('-');
        return Number(a) === new Date().getFullYear() ? `${d}/${m}` : `${d}/${m}/${a}`;
    };

    const chipsDeFechas = (fechas) => {
        if (!fechas?.length) return '';
        const visibles = fechas.slice(0, 4).map((f) => `<span class="t-chip-fecha">${fechaCorta(f)}</span>`).join('');
        const resto = fechas.length - 4;
        const todas = Tads.escapar(fechas.map((f) => f.split('-').reverse().join('/')).join(', '));

        return `<div class="t-problema-fechas">${visibles}${resto > 0 ? `<span class="t-chip-fecha t-chip-mas" title="${todas}">+${resto} más</span>` : ''}</div>`;
    };

    const tarjetaDeProblema = (p, indice, conBoton, oculta, pista = '') => `
        <li class="t-problema${oculta ? ' t-problema-oculto' : ''}">
            <span class="t-problema-icono"><i class="bi ${ICONOS[p.tipo] ?? 'bi-exclamation-circle-fill'}"></i></span>
            <div class="t-problema-cuerpo">
                <div class="t-problema-linea">
                    ${p.grupo && p.grupo !== p.quien ? `<span class="t-problema-grupo">${Tads.escapar(p.grupo)}</span>` : ''}
                    ${p.quien ? `<strong>${Tads.escapar(p.quien)}</strong>` : ''}
                    <span class="t-problema-que">${Tads.escapar(p.que)}</span>
                </div>
                ${chipsDeFechas(p.fechas)}
                ${p.solucion ? `
                    <div class="t-problema-solucion">
                        <i class="bi bi-lightbulb-fill"></i>
                        ${p.accion && conBoton
                            ? `<button type="button" class="t-aplicar" data-aplicar="${indice}"><i class="bi bi-arrow-repeat me-1"></i>${Tads.escapar(p.solucion)}</button>`
                            : `<span>${Tads.escapar(p.solucion)}${p.accion && pista ? ` <small class="text-muted">· ${Tads.escapar(pista)}</small>` : ''}</span>`}
                    </div>` : ''}
            </div>
        </li>`;

    const notasHtml = (notas, avisos) => {
        const textos = notas?.length ? notas.map((n) => (n.grupo ? `${n.grupo}: ${n.texto}` : n.texto)) : (avisos ?? []);
        return textos.length
            ? `<div class="t-notas">${textos.map((t) => `<span class="t-nota-chip"><i class="bi bi-info-circle-fill"></i>${Tads.escapar(t)}</span>`).join('')}</div>`
            : '';
    };

    /**
     * Resultado de «Validar disponibilidad»: pocas líneas y una tarjeta por problema (quién, qué pasa, qué días y la solución).
     * `alAplicar` es opcional: si la pantalla puede ejecutar el cambio sugerido, la solución es un botón.
     */
    function pintarResultado(panel, resultado, esReemplazo = false, alAplicar = null, puedeAplicar = null, pista = '') {
        const notas = notasHtml(resultado.notas, resultado.avisos);

        if (resultado.ok) {
            const total = resultado.total;
            const que = esReemplazo
                ? (total === 1 ? 'se reemplazará en 1 programación' : `se reemplazará en ${total} programaciones`)
                : (total === 1 ? 'se generará 1 programación' : `se generarán ${total} programaciones`);
            panel.innerHTML = `
                <div class="t-veredicto t-veredicto-ok" role="status">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><strong>Todo en orden</strong> · ${que}</span>
                </div>${notas}`;
            return;
        }

        const problemas = resultado.problemas ?? [];

        // Sin datos estructurados (no debería pasar): se muestran las listas de texto de siempre.
        if (!problemas.length) {
            panel.innerHTML = `<div class="t-validacion t-validacion-error" role="alert"><strong>Todavía no se puede guardar</strong>${lista(resultado.errores)}</div>${notas}`;
            return;
        }

        const cuantos = problemas.length;
        panel.innerHTML = `
            <div class="t-veredicto t-veredicto-error" role="alert">
                <div class="t-veredicto-cabecera">
                    <i class="bi bi-x-octagon-fill"></i>
                    <strong>No se puede guardar todavía</strong>
                    <span class="t-veredicto-contador">${cuantos} ${cuantos === 1 ? 'cosa por corregir' : 'cosas por corregir'}</span>
                </div>
                <ul class="t-problemas">${problemas.map((p, i) => tarjetaDeProblema(p, i, !!alAplicar && (!puedeAplicar || puedeAplicar(p.accion)), i >= POR_PANTALLA, pista)).join('')}</ul>
                ${cuantos > POR_PANTALLA ? `<button type="button" class="t-ver-mas" data-ver-mas>Ver ${cuantos - POR_PANTALLA} más</button>` : ''}
            </div>${notas}`;

        panel.querySelector('[data-ver-mas]')?.addEventListener('click', (e) => {
            panel.querySelectorAll('.t-problema-oculto').forEach((el) => el.classList.remove('t-problema-oculto'));
            e.currentTarget.remove();
        });

        panel.querySelectorAll('[data-aplicar]').forEach((boton) => boton.addEventListener('click', () => alAplicar?.(problemas[Number(boton.dataset.aplicar)].accion, problemas[Number(boton.dataset.aplicar)])));
    }

    /** Conecta "Validar disponibilidad" con el botón de guardar: solo se activa tras una validación correcta. */
    function activarValidacion(cuerpo, alAplicar = null, puedeAplicar = null, pista = '') {
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

        // El motivo de un cambio no afecta a la disponibilidad: escribirlo no obliga a validar otra vez.
        const alCambiar = (e) => { if (!e.target.closest?.('[data-motivo]')) invalidar(); };
        formulario.addEventListener('input', alCambiar);
        formulario.addEventListener('change', alCambiar);

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

            formulario.dispatchEvent(new CustomEvent('tads:validado', { detail: json }));

            if (formulario._pintarResultado) {
                formulario._pintarResultado(json, panel);
                hayResultado = true;
                botonGuardar.disabled = !json.ok;
                panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

                return;
            }

            pintarResultado(panel, json, formulario.hasAttribute('data-reemplazo'), alAplicar && ((accion, problema) => {
                alAplicar(accion, problema);
                setTimeout(() => botonValidar.click(), 250);   // se vuelve a validar solo con el cambio ya hecho
            }), puedeAplicar, pista);
            hayResultado = true;
            botonGuardar.disabled = !json.ok;
            panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    }

    /* ------------------------------------------------------------------ formularios de programación */

    /** Pone en el formulario a la persona sugerida en lugar de la que tiene el problema. */
    function aplicarCambio(formulario, accion) {
        if (formulario.hasAttribute('data-reemplazo')) {
            formulario.querySelector('[data-par]:not([hidden]) [name="entrante_id"]')?.closest('.t-buscador')?.tadsElegir(accion.por);
            return;
        }

        if (accion.rol === 'conductor') {
            formulario.querySelector('[data-conductor]')?.tadsElegir(accion.por);
            return;
        }

        const raiz = [...formulario.querySelectorAll('[data-ranuras-ayudantes] .t-buscador')]
            .find((r) => String(r.querySelector('input[type="hidden"]')?.value) === String(accion.persona_id));
        raiz?.tadsElegir(accion.por);
    }

    function activarFormularioDeProgramacion(cuerpo, gestor = null) {
        const formulario = cuerpo.querySelector('form[data-programacion-formulario]');
        if (!formulario) return;

        if (formulario.hasAttribute('data-modificar')) return activarModificacion(cuerpo, formulario, gestor);
        if (formulario.hasAttribute('data-cambio-masivo')) return activarCambioMasivo(cuerpo, formulario);

        const esMasiva = formulario.hasAttribute('data-masiva');
        const esReemplazo = formulario.hasAttribute('data-reemplazo');
        // En el reemplazo solo se puede aplicar el cambio a quien está entrando; en la masiva no hay un campo donde hacerlo.
        const entrante = () => formulario.querySelector('[data-par]:not([hidden]) [name="entrante_id"]');
        activarValidacion(
            cuerpo,
            esMasiva ? null : (accion) => aplicarCambio(formulario, accion),
            esReemplazo ? (accion) => !!accion && String(entrante()?.value) === String(accion.persona_id) : null,
        );

        if (formulario.hasAttribute('data-masiva')) return activarProgramacionMasiva(formulario);
        if (formulario.hasAttribute('data-reemplazo')) return activarReemplazo(formulario);
        const contenedor = formulario.querySelector('[data-ranuras-ayudantes]');
        const grupo = formulario.querySelector('[data-grupo]');
        const vehiculo = formulario.querySelector('[data-vehiculo]');
        const conductor = formulario.querySelector('[data-conductor]');

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

    /* ------------------------------------------------------------------ cambio masivo */

    /**
     * Nuevo cambio masivo: tipo de cambio (turno, vehículo, conductor u ocupante), zonas opcionales y motivo. La descripción completa se
     * arma sola con el motivo y el complemento. Al guardar se valida y se pide confirmación (lo hace el módulo de cambios).
     */
    function activarCambioMasivo(cuerpo, formulario) {
        const rol = formulario.querySelector('[name="rol"]');
        const pares = [...formulario.querySelectorAll('[data-par]')];
        const panel = formulario.querySelector('[data-resultado]');
        const motivo = formulario.querySelector('[data-motivo-select]');
        const adicional = formulario.querySelector('[data-motivo-adicional]');
        const texto = formulario.querySelector('[data-motivo-detalle]');
        const elegirZona = formulario.querySelector('[data-zona-elegir]');
        const zonas = formulario.querySelector('[data-zonas-elegidas]');

        const mostrar = () => pares.forEach((par) => {
            const activo = par.dataset.par === rol.value;
            par.hidden = !activo;
            par.querySelectorAll('input, select').forEach((campo) => { campo.disabled = !activo; });
        });
        rol.addEventListener('change', mostrar);
        mostrar();

        // Zonas: las elegidas quedan como etiquetas; sin ninguna, el cambio vale para todas.
        elegirZona.addEventListener('change', () => {
            const id = elegirZona.value;
            if (id && !zonas.querySelector(`[data-zona="${id}"]`)) {
                zonas.insertAdjacentHTML('beforeend', `<span class="t-insignia t-azul" data-zona="${id}">${Tads.escapar(elegirZona.selectedOptions[0].textContent)}
                    <input type="hidden" name="zona_ids[]" value="${id}"><button type="button" class="btn-close ms-1" style="font-size:.55rem" data-quitar-zona aria-label="Quitar zona"></button></span>`);
            }
            elegirZona.value = '';
            formulario.dispatchEvent(new Event('change'));
        });
        zonas.addEventListener('click', (e) => {
            const quitar = e.target.closest('[data-quitar-zona]');
            if (quitar) { quitar.closest('[data-zona]').remove(); formulario.dispatchEvent(new Event('change')); }
        });

        // La descripción completa se arma con el motivo elegido y el complemento (y se puede editar a mano).
        const componer = () => { texto.value = [motivo.value ? motivo.selectedOptions[0].dataset.nombre : '', adicional.value.trim()].filter(Boolean).join(' - '); };
        motivo.addEventListener('change', componer);
        adicional.addEventListener('input', componer);

        // Cualquier cambio de datos borra el resultado anterior.
        formulario.addEventListener('change', () => { panel.innerHTML = ''; });
        formulario.addEventListener('input', (e) => { if (!e.target.matches('[data-motivo-adicional], [data-motivo-detalle]')) panel.innerHTML = ''; });

        const par = () => pares.find((x) => !x.hidden);
        const elegido = (campo) => {
            const control = par().querySelector(`[name="${campo}"]`);
            if (control?.tagName === 'SELECT') return control.selectedOptions[0]?.textContent.trim() ?? '';

            return control?.closest('.t-buscador')?._tadsItem?.titulo ?? '';
        };

        /** Lo que verá el usuario en la ventana de confirmación. */
        formulario._resumen = () => ({
            tipo: rol.selectedOptions[0].textContent.trim(), inicio: formulario.querySelector('[name="desde"]').value, fin: formulario.querySelector('[name="hasta"]').value,
            zonas: [...zonas.querySelectorAll('[data-zona]')].map((z) => z.firstChild.textContent.trim()),
            saliente: elegido('saliente_id'), entrante: elegido('entrante_id'),
            motivo: texto.value.trim() || (motivo.value ? motivo.selectedOptions[0].dataset.nombre : ''),
        });

        /** Valida con el servidor y pinta el resultado. Devuelve el JSON, o null si hay datos incompletos. */
        formulario._validar = async () => {
            Tads.limpiarErrores(formulario);
            if (!formulario.checkValidity()) { formulario.reportValidity(); return null; }

            if (!motivo.value && !texto.value.trim()) {
                const alerta = formulario.querySelector('.t-alerta-form');
                alerta.textContent = 'Elija un motivo predefinido o escriba la descripción del cambio.';
                alerta.classList.add('visible');
                motivo.classList.add('is-invalid');

                return null;
            }

            const { ok, estado, json } = await Tads.peticion(formulario.dataset.urlValidar, { metodo: 'POST', cuerpo: new FormData(formulario) });
            if (!ok) { if (estado === 422) Tads.mostrarErrores(formulario, json); else Tads.alertaDeError(estado, json, 'No se pudo validar la disponibilidad'); return null; }

            pintarResultado(panel, json, true);
            panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

            return json;
        };

        formulario.querySelector('[data-validar]').addEventListener('click', async (e) => {
            const boton = e.currentTarget;
            const original = boton.innerHTML;
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Validando...';
            await formulario._validar();
            boton.disabled = false;
            boton.innerHTML = original;
        });
        motivo.addEventListener('change', () => motivo.classList.remove('is-invalid'));
    }

    /* ------------------------------------------------------------------ modificar una programación */

    const NOMBRES_TIPO = { turno: 'Turno', vehiculo: 'Vehículo', conductor: 'Conductor', ayudante: 'Ocupante' };

    /**
     * Modificar: tres paneles (turno, vehículo, personal). Cada cambio se valida al elegirlo, lleva su motivo y se agrega a la
     * tabla «Cambios registrados»; al guardar se aplican todos juntos. Debajo, el historial de la programación con «Deshacer».
     */
    function activarModificacion(cuerpo, formulario, gestor) {
        const original = JSON.parse(formulario.dataset.estado);
        const botonGuardar = formulario.querySelector('[data-guardar]');
        const notas = formulario.querySelector('[name="notas"]');
        const tabla = formulario.querySelector('[data-cambios-nuevos]');
        const sinCambios = formulario.querySelector('[data-sin-cambios]');
        const personalActual = formulario.querySelector('[data-actual]');
        let colocados = [];

        const panel = (nombre) => formulario.querySelector(`[data-panel="${nombre}"]`);
        const textoDe = (select) => select.selectedOptions[0]?.dataset.texto ?? select.selectedOptions[0]?.textContent.trim() ?? '';

        const aplicar = (lista) => {
            const e = { turno: original.turno, vehiculo: original.vehiculo, conductor: original.conductor, ayudantes: [...original.ayudantes] };
            lista.forEach((c) => {
                if (c.tipo === 'turno') e.turno = c.nuevoId;
                else if (c.tipo === 'vehiculo') e.vehiculo = c.nuevoId;
                else if (c.tipo === 'conductor') e.conductor = c.nuevoId;
                else if (c.nuevoId === null) e.ayudantes = e.ayudantes.slice(0, c.indice);
                else e.ayudantes[c.indice] = c.nuevoId;
            });
            return e;
        };

        const aviso = (p, tipo, texto) => {
            const caja = p.querySelector('[data-aviso]');
            caja.hidden = !texto;
            caja.className = `av-aviso t-validacion t-validacion-${tipo === 'mal' ? 'error' : tipo}`;
            caja.innerHTML = texto ? `<i class="bi ${tipo === 'ok' ? 'bi-check-circle-fill' : tipo === 'mal' ? 'bi-x-octagon-fill' : /\.\.\.$/.test(texto) ? 'bi-hourglass-split' : 'bi-info-circle-fill'}"></i><span>${Tads.escapar(texto)}</span>` : '';
        };

        const motivoListo = (p) => !!(p.querySelector('[data-motivo-select]').value || p.querySelector('[data-motivo-detalle]').value.trim());

        /** Pregunta al servidor si el equipo quedaría bien con ese estado. */
        async function validarEstado(estado) {
            const datos = new FormData();
            datos.append('turno_id', estado.turno);
            datos.append('vehiculo_id', estado.vehiculo);
            datos.append('conductor_id', estado.conductor);
            estado.ayudantes.forEach((a) => datos.append('ayudantes[]', a));
            const { ok, estado: codigo, json } = await Tads.peticion(formulario.dataset.urlValidar, { metodo: 'POST', cuerpo: datos });
            if (!ok) return { ok: false, texto: Tads.textoDeError(codigo, json).split('\n')[0] };
            if (json.ok) return { ok: true };

            const p = json.problemas?.[0];
            return { ok: false, texto: p ? `${p.quien ? `${p.quien}: ` : ''}${p.que}${p.solucion ? `. ${p.solucion}` : ''}` : (json.errores?.[0] ?? 'No está disponible') };
        }

        /** Estado que resultaría de agregar `cambio` (reemplaza al que ya hubiera en ese mismo dato). */
        const prospectivo = (cambio) => aplicar([...colocados.filter((c) => c.campo !== cambio.campo), cambio]);

        const refrescarBoton = (p) => {
            p.querySelector('[data-agregar]').disabled = !(p._valido && motivoListo(p));
        };

        /** Parte común de los tres paneles: motivo que se completa solo y botón «Agregar». */
        function enlazarMotivo(p) {
            const lista = p.querySelector('[data-motivo-select]');
            const texto = p.querySelector('[data-motivo-detalle]');
            let autocompletado = '';

            lista.addEventListener('change', () => {
                const opcion = lista.selectedOptions[0];
                const propuesta = lista.value ? (opcion.dataset.descripcion || opcion.dataset.nombre) : '';
                if (texto.value.trim() === '' || texto.value === autocompletado) texto.value = propuesta;
                autocompletado = propuesta;
                refrescarBoton(p);
            });
            texto.addEventListener('input', () => refrescarBoton(p));
        }

        function limpiarPanel(p) {
            p._valido = false;
            p.querySelector('[data-nuevo]').value = '';
            p.querySelector('[data-motivo-select]').value = '';
            p.querySelector('[data-motivo-detalle]').value = '';
            aviso(p, 'info', '');
            refrescarBoton(p);
        }

        function pintarTabla() {
            sinCambios.hidden = colocados.length > 0;
            tabla.innerHTML = colocados.map((c, i) => `
                <tr>
                    <td>${Tads.insignia(NOMBRES_TIPO[c.tipo], 'ambar')}</td>
                    <td>${Tads.escapar(c.antesTexto)}</td>
                    <td><strong>${Tads.escapar(c.nuevoTexto)}</strong></td>
                    <td>${Tads.escapar(c.motivoNombre || 'Motivo propio')}</td>
                    <td>${Tads.escapar(c.descripcion || '—')}</td>
                    <td class="text-center"><button type="button" class="btn t-boton-icono t-eliminar" data-quitar-cambio="${i}" title="Quitar este cambio"><i class="bi bi-x-lg"></i></button></td>
                </tr>`).join('');
        }

        function sincronizar() {
            const e = aplicar(colocados);
            const destino = formulario.querySelector('[data-campos-ocultos]');
            destino.innerHTML = '';
            const campo = (nombre, valor) => {
                const i = document.createElement('input');
                i.type = 'hidden'; i.name = nombre; i.value = valor;
                destino.appendChild(i);
            };
            campo('turno_id', e.turno);
            campo('vehiculo_id', e.vehiculo);
            campo('conductor_id', e.conductor);
            e.ayudantes.forEach((a) => campo('ayudantes[]', a));
            colocados.forEach((c) => {
                if (c.motivoId) campo(`motivos[${c.campo}][motivo_id]`, c.motivoId);
                campo(`motivos[${c.campo}][descripcion]`, c.descripcion);
            });

            botonGuardar.disabled = !(colocados.length || (notas?.value ?? '') !== (formulario.dataset.notas ?? ''));
        }

        function agregar(p, cambio) {
            const lista = p.querySelector('[data-motivo-select]');
            colocados = colocados.filter((c) => c.campo !== cambio.campo);
            colocados.push({
                ...cambio, motivoId: lista.value, motivoNombre: lista.value ? lista.selectedOptions[0].dataset.nombre : '',
                descripcion: p.querySelector('[data-motivo-detalle]').value.trim(),
            });
            limpiarPanel(p);
            if (p.dataset.panel === 'personal') { personalActual.value = ''; reiniciarNuevoPersonal(); }
            pintarTabla();
            sincronizar();
        }

        /* ---- turno y vehículo ---- */
        ['turno', 'vehiculo'].forEach((nombre) => {
            const p = panel(nombre);
            const nuevo = p.querySelector('[data-nuevo]');
            const textoActual = p.querySelector('input[readonly]').value;
            p._valido = false;
            enlazarMotivo(p);

            nuevo.addEventListener('change', async () => {
                p._valido = false;
                refrescarBoton(p);
                if (!nuevo.value) return aviso(p, 'info', '');

                const mia = (p._consulta = (p._consulta ?? 0) + 1);
                aviso(p, 'info', 'Comprobando disponibilidad...');
                const cambio = { campo: nombre, tipo: nombre, nuevoId: Number(nuevo.value) };
                const r = await validarEstado(prospectivo(cambio));
                if (mia !== p._consulta) return;

                p._valido = r.ok;
                aviso(p, r.ok ? 'ok' : 'mal', r.ok ? `${nombre === 'turno' ? 'Turno' : 'Vehículo'} disponible para el cambio` : r.texto);
                refrescarBoton(p);
            });

            p.querySelector('[data-agregar]').addEventListener('click', () => agregar(p, {
                campo: nombre, tipo: nombre, antesTexto: textoActual, nuevoId: Number(nuevo.value), nuevoTexto: textoDe(nuevo),
            }));
        });

        /* ---- personal ---- */
        const pp = panel('personal');
        const nuevoPersonal = pp.querySelector('[data-nuevo]');
        pp._valido = false;
        enlazarMotivo(pp);

        function reiniciarNuevoPersonal() {
            nuevoPersonal.innerHTML = '<option value="">Buscar empleado disponible...</option>';
            nuevoPersonal.disabled = true;
            pp._valido = false;
            aviso(pp, 'info', '');
            refrescarBoton(pp);
        }

        const datosDeActual = () => {
            const opcion = personalActual.selectedOptions[0];
            const campo = opcion.dataset.campo;
            const rol = opcion.dataset.rol;
            return { opcion, campo, rol, indice: rol === 'ayudante' ? Number(campo.split('_')[1]) - 1 : -1 };
        };

        personalActual.addEventListener('change', async () => {
            reiniciarNuevoPersonal();
            if (!personalActual.value) return;

            const { rol, indice } = datosDeActual();
            const hoy = aplicar(colocados);
            const ocupados = new Set([hoy.conductor, ...hoy.ayudantes].map(String));
            const elegida = personalActual.value;

            aviso(pp, 'info', 'Buscando personal disponible...');
            const url = `${formulario.dataset.urlCandidatos}?${new URLSearchParams({ rol, saliente_id: elegida, turno_id: hoy.turno })}`;
            const { ok, estado, json } = await Tads.peticion(url);
            if (personalActual.value !== elegida) return;   // eligió a otra persona mientras tanto
            if (!ok) return aviso(pp, 'mal', Tads.textoDeError(estado, json).split('\n')[0]);

            const libres = json.data.filter((c) => c.disponible && !ocupados.has(String(c.persona.id)));
            const otros = json.data.filter((c) => !c.disponible && !ocupados.has(String(c.persona.id)));
            // A un ayudante se lo puede quitar sin reemplazo solo si es el último y queda alguien más.
            const puedeQuitar = rol === 'ayudante' && indice === original.ayudantes.length - 1 && original.ayudantes.length > 1;

            nuevoPersonal.innerHTML = '<option value="">Seleccione un nuevo personal</option>'
                + (puedeQuitar ? '<option value="quitar" data-texto="(se quita del equipo)">— Quitar del equipo (sin reemplazo) —</option>' : '')
                + (libres.length ? `<optgroup label="Disponibles">${libres.map((c) => `<option value="${c.persona.id}" data-texto="${Tads.escapar(c.persona.titulo)}">${Tads.escapar(c.persona.titulo)}</option>`).join('')}</optgroup>` : '')
                + (otros.length ? `<optgroup label="No disponibles">${otros.map((c) => `<option value="${c.persona.id}" disabled>${Tads.escapar(c.persona.titulo)} — ${Tads.escapar(c.motivo)}</option>`).join('')}</optgroup>` : '');
            nuevoPersonal.disabled = false;
            aviso(pp, libres.length || puedeQuitar ? 'info' : 'mal', libres.length ? `${libres.length} ${libres.length === 1 ? 'persona disponible' : 'personas disponibles'}` : 'No hay personal disponible para ese puesto');
        });

        const cambioDePersonal = () => {
            const { campo, rol, indice } = datosDeActual();
            const quitar = nuevoPersonal.value === 'quitar';

            return { campo, tipo: rol, indice, nuevoId: quitar ? null : Number(nuevoPersonal.value) };
        };

        nuevoPersonal.addEventListener('change', async () => {
            pp._valido = false;
            refrescarBoton(pp);
            if (!nuevoPersonal.value) return;

            const mia = (pp._consulta = (pp._consulta ?? 0) + 1);
            aviso(pp, 'info', 'Comprobando disponibilidad...');
            const r = await validarEstado(prospectivo(cambioDePersonal()));
            if (mia !== pp._consulta) return;

            pp._valido = r.ok;
            aviso(pp, r.ok ? 'ok' : 'mal', r.ok ? 'Personal disponible para el cambio' : r.texto);
            refrescarBoton(pp);
        });

        pp.querySelector('[data-agregar]').addEventListener('click', () => {
            agregar(pp, { ...cambioDePersonal(), antesTexto: datosDeActual().opcion.dataset.texto, nuevoTexto: textoDe(nuevoPersonal) });
        });

        tabla.addEventListener('click', (evento) => {
            const quitar = evento.target.closest('[data-quitar-cambio]');
            if (!quitar) return;
            colocados.splice(Number(quitar.dataset.quitarCambio), 1);
            pintarTabla();
            sincronizar();
        });

        notas?.addEventListener('input', sincronizar);
        sincronizar();
        pintarTabla();

        // Desde el dashboard: se abre ya con el personal que falta elegido, listo para elegir a quien lo reemplaza.
        if (formulario.dataset.primeraFalta) {
            const opcion = [...personalActual.options].find((o) => o.dataset.campo === formulario.dataset.primeraFalta);
            if (opcion) { personalActual.value = opcion.value; personalActual.dispatchEvent(new Event('change')); }
        }

        // Historial: deshacer un cambio y volver a abrir la ventana ya actualizada.
        cuerpo.querySelectorAll('[data-deshacer-cambio]').forEach((boton) => boton.addEventListener('click', async () => {
            const decision = await Swal.fire({
                title: '¿Deshacer este cambio?',
                html: `<strong>${Tads.escapar(boton.dataset.texto)}</strong><br>La programación recuperará el valor anterior (si sigue disponible).`,
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, deshacer', cancelButtonText: 'Cancelar', reverseButtons: true,
            });
            if (!decision.isConfirmed) return;

            const { ok, estado, json } = await Tads.peticion(`${formulario.dataset.urlCambios}/${boton.dataset.deshacerCambio}?cambio_id=${boton.dataset.cambio}`,
                { metodo: 'POST', cuerpo: Tads.cuerpoConMetodo('DELETE') });
            if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo deshacer el cambio');

            Tads.aviso(json.mensaje);
            gestor?.recargar();
            gestor?.alConfirmarCierre?.();
            gestor?.abrirFormulario(formulario.dataset.urlEditar, gestor.tituloEl.innerHTML);
        }));
    }

    /* ------------------------------------------------------------------ programación masiva */

    const NOMBRES_DIA = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo' };
    const fechaLarga = (iso) => iso.split('-').reverse().join('/');
    const textoDeFechas = (fechas, mostrar = 3) => {
        const f = fechas.map(fechaLarga);
        return f.length > mostrar ? `${f.slice(0, mostrar).join(', ')} y ${f.length - mostrar} más` : f.join(', ');
    };

    /**
     * Programación masiva: una tarjeta por grupo con su conductor y ayudantes editables. «Validar disponibilidad» repite la validación
     * individual grupo por grupo: cada campo con conflicto se marca y abajo queda el resultado por grupo (errores y advertencias).
     */
    function activarProgramacionMasiva(formulario) {
        const grupos = JSON.parse(formulario.dataset.grupos);
        const conductores = JSON.parse(formulario.dataset.conductores);
        const ayudantes = JSON.parse(formulario.dataset.ayudantes);
        const grid = formulario.querySelector('[data-grupos-grid]');
        const contador = formulario.querySelector('[data-contador-grupos]');
        const restaurar = formulario.querySelector('[data-restaurar]');
        const inicio = formulario.querySelector('[name="inicio"]');
        const fin = formulario.querySelector('[name="fin"]');
        const botonesTurno = [...formulario.querySelectorAll('[data-filtro-turnos] button')];
        const cajaFeriados = formulario.querySelector('[data-feriados-caja]');
        const quitados = new Set();
        let turnoFiltro = '';

        const NOTA_INICIAL = 'Seleccione fechas para validar';
        const nota = (texto = NOTA_INICIAL, clase = '') => `<div class="av-campo-nota ${clase}" data-nota><i class="bi bi-info-circle-fill"></i><span>${texto}</span></div>`;
        const opciones = (lista, elegido) => lista.map((p) => `<option value="${p.id}" ${p.id === elegido ? 'selected' : ''}>${Tads.escapar(p.nombre)}</option>`).join('');

        if (!grid) return;

        grid.innerHTML = grupos.map((g) => `
            <article class="av-grupo" data-grupo="${g.id}" data-turno="${g.turno_id}">
                <header>
                    <strong>${Tads.escapar(g.nombre)}</strong>
                    <button type="button" class="btn t-boton-icono t-eliminar" data-quitar data-bs-toggle="tooltip" title="Quitar este grupo de la programación" aria-label="Quitar ${Tads.escapar(g.nombre)}"><i class="bi bi-x-lg"></i></button>
                </header>
                <dl>
                    <dt>Zona</dt><dd>${Tads.escapar(g.zona ?? '—')}</dd>
                    <dt>Turno</dt><dd>${Tads.insignia(g.turno ?? '—', 'ambar')}</dd>
                    <dt>Días</dt><dd>${g.dias.map((d) => `<span class="t-chip-fecha">${NOMBRES_DIA[d].slice(0, 3)}</span>`).join(' ')}</dd>
                    <dt>Vehículo</dt><dd>${g.vehiculo ? `<span class="t-codigo">${Tads.escapar(g.vehiculo.placa)}</span> <small class="text-muted">cap. ${g.vehiculo.capacidad}</small>` : '—'}</dd>
                </dl>
                <input type="hidden" name="grupos[]" value="${g.id}">
                <label class="form-label">Conductor:</label>
                <select class="form-select form-select-sm" name="detalle[${g.id}][conductor_id]" data-persona>${opciones(conductores, g.conductor)}</select>${nota()}
                ${g.ayudantes.map((a, i) => `
                    <label class="form-label">Ayudante ${i + 1}:</label>
                    <select class="form-select form-select-sm" name="detalle[${g.id}][ayudantes][]" data-persona>${opciones(ayudantes, a)}</select>${nota()}`).join('')}
            </article>`).join('');

        const tarjetas = [...grid.querySelectorAll('[data-grupo]')];
        const incluida = (t) => !quitados.has(t.dataset.grupo) && (turnoFiltro === '' || t.dataset.turno === turnoFiltro);

        /** Una tarjeta fuera de la programación (quitada o filtrada) se oculta y sus campos no se envían. */
        function actualizar() {
            tarjetas.forEach((t) => {
                const dentro = incluida(t);
                t.hidden = !dentro;
                t.querySelectorAll('input, select').forEach((c) => { c.disabled = !dentro; });
            });
            contador.textContent = tarjetas.filter(incluida).length;
            restaurar.hidden = quitados.size === 0;
        }

        function limpiarNotas() {
            tarjetas.forEach((t) => {
                t.classList.remove('con-error', 'sin-error');
                t.querySelectorAll('[data-nota]').forEach((n) => n.replaceWith(Object.assign(document.createElement('div'), { innerHTML: nota() }).firstElementChild));
                t.querySelectorAll('select').forEach((s) => s.classList.remove('is-invalid-av'));
            });
        }

        grid.addEventListener('click', (evento) => {
            const quitar = evento.target.closest('[data-quitar]');
            const por = evento.target.closest('[data-cambiar-por]');

            if (quitar) {
                quitados.add(quitar.closest('[data-grupo]').dataset.grupo);
                actualizar();
                formulario.dispatchEvent(new Event('change'));
            }

            if (por) {
                const select = por.closest('[data-nota]').previousElementSibling;
                select.value = por.dataset.cambiarPor;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                setTimeout(() => formulario.querySelector('[data-validar]').click(), 250);   // se vuelve a validar solo con el cambio ya hecho
            }
        });

        restaurar.addEventListener('click', () => { quitados.clear(); actualizar(); formulario.dispatchEvent(new Event('change')); });

        botonesTurno.forEach((b) => b.addEventListener('click', () => {
            turnoFiltro = b.dataset.turno;
            botonesTurno.forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
            actualizar();
            formulario.dispatchEvent(new Event('change'));
        }));

        formulario.addEventListener('change', (e) => { if (!e.target.closest('[data-filtro-turnos]')) limpiarNotas(); });

        /* ---- feriados del rango: lo marcado NO se programa ---- */
        const usar = Object.assign(document.createElement('div'), { hidden: true });
        formulario.appendChild(usar);
        let consulta = 0;

        // Los feriados desmarcados sí se trabajan: viajan como «usar_feriados[]» (los marcados se omiten, como siempre).
        const sincronizarFeriados = () => {
            usar.innerHTML = [...cajaFeriados.querySelectorAll('input[type="checkbox"]:not(:checked)')]
                .map((c) => `<input type="hidden" name="usar_feriados[]" value="${c.value}">`).join('');
        };

        async function cargarFeriados() {
            if (!inicio.value || !fin.value || fin.value < inicio.value) {
                cajaFeriados.textContent = 'Elija las fechas para ver los feriados.';
                return sincronizarFeriados();
            }

            const mia = ++consulta;
            const desmarcados = new Set([...cajaFeriados.querySelectorAll('input[type="checkbox"]:not(:checked)')].map((c) => c.value));
            const { ok, json } = await Tads.peticion(`${formulario.dataset.urlFeriados}?${new URLSearchParams({ inicio: inicio.value, fin: fin.value })}`);
            if (!ok || mia !== consulta) return;

            cajaFeriados.innerHTML = json.data.length
                ? `<div class="av-feriados-lista">${json.data.map((f) => `
                    <label class="t-dia"><input type="checkbox" value="${f.fecha}" ${desmarcados.has(f.fecha) ? '' : 'checked'}>
                        <span>${Tads.escapar(f.texto)} · ${Tads.escapar(f.descripcion)} <small class="text-muted">(${Tads.escapar(f.dia)})</small></span></label>`).join('')}</div>`
                : 'No hay feriados en este rango.';
            sincronizarFeriados();
        }

        cajaFeriados.addEventListener('change', (e) => { e.stopPropagation(); sincronizarFeriados(); formulario.dispatchEvent(new Event('change')); });
        inicio.addEventListener('change', () => { fin.min = inicio.value || fin.min; if (fin.value && fin.value < inicio.value) fin.value = inicio.value; cargarFeriados(); });
        fin.addEventListener('change', cargarFeriados);

        /* ---- resultado de la validación, grupo por grupo ---- */
        const diasNoCubiertos = (g) => {
            if (!inicio.value || !fin.value) return 0;
            let cuantos = 0;
            for (let d = new Date(`${inicio.value}T12:00:00`); d <= new Date(`${fin.value}T12:00:00`); d.setDate(d.getDate() + 1)) {
                if (!g.dias.includes(d.getDay() || 7)) cuantos++;
            }
            return cuantos;
        };

        formulario._pintarResultado = (json, panel) => {
            limpiarNotas();
            const dentro = tarjetas.filter(incluida);
            const unico = dentro.length === 1;
            const estructurales = (json.problemas ?? []).filter((p) => ['fechas', 'dias', 'limite'].includes(p.tipo));
            const bloques = [];

            dentro.forEach((t) => {
                const g = grupos.find((x) => String(x.id) === t.dataset.grupo);
                const suyos = (json.problemas ?? []).filter((p) => !['fechas', 'dias', 'limite'].includes(p.tipo) && (p.grupo === g.nombre || (unico && !p.grupo)));
                const avisos = (json.notas ?? []).filter((n) => n.grupo === g.nombre || (unico && !n.grupo)).map((n) => ({ texto: n.texto }));

                t.classList.add(suyos.length ? 'con-error' : 'sin-error');

                // El mensaje de cada persona queda justo debajo de su campo.
                t.querySelectorAll('select[data-persona]').forEach((select) => {
                    const delCampo = suyos.filter((p) => String(p.persona_id) === select.value);
                    const destino = select.nextElementSibling;
                    if (!delCampo.length) { destino.outerHTML = nota(suyos.length ? 'Sin conflictos propios' : 'Disponible en las fechas elegidas', 'ok'); return; }

                    select.classList.add('is-invalid-av');
                    destino.outerHTML = nota(delCampo.map((p) => `<strong>${Tads.escapar(p.que)}</strong>${p.fechas?.length ? `: ${textoDeFechas(p.fechas)}` : ''}`
                        + `${p.accion?.por ? ` · <button type="button" class="t-aplicar" data-cambiar-por="${p.accion.por.id}"><i class="bi bi-arrow-repeat me-1"></i>Cambiar por ${Tads.escapar(p.accion.por.titulo)}</button>` : ''}`).join('<br>'), 'mal');
                });

                const sinCubrir = diasNoCubiertos(g);
                bloques.push(`
                    <details class="av-res-grupo" ${suyos.length ? 'open' : ''}>
                        <summary>
                            <i class="bi bi-people-fill text-muted"></i>
                            <strong>${Tads.escapar(g.nombre)} <small class="text-muted fw-normal">${Tads.escapar(g.zona ?? '')} · ${Tads.escapar(g.turno ?? '')}</small></strong>
                            ${suyos.length ? Tads.insignia('Con errores', 'rojo') : Tads.insignia('Sin errores', 'verde')}
                            ${avisos.length ? Tads.insignia('Con advertencias', 'ambar') : ''}
                            <i class="bi bi-chevron-down text-muted"></i>
                        </summary>
                        <div>
                            ${sinCubrir ? `<div class="small text-muted mb-2"><i class="bi bi-calendar-x me-1"></i><strong>Días sin programar:</strong> ${sinCubrir} ${sinCubrir === 1 ? 'día' : 'días'} del rango (el grupo solo trabaja: ${g.dias.map((d) => NOMBRES_DIA[d]).join(', ')}).</div>` : ''}
                            ${suyos.length ? `<ul class="t-problemas">${suyos.map((p, k) => tarjetaDeProblema(p, k, false, false)).join('')}</ul>` : '<div class="small text-success"><i class="bi bi-check-circle-fill me-1"></i>Sin errores en este grupo.</div>'}
                            ${notasHtml(avisos, [])}
                        </div>
                    </details>`);
            });

            const conProblemas = tarjetas.filter((t) => t.classList.contains('con-error')).length;
            panel.innerHTML = json.ok
                ? `<div class="t-veredicto t-veredicto-ok" role="status"><i class="bi bi-check-circle-fill"></i>
                        <span><strong>Todo en orden</strong> · ${json.total === 1 ? 'se generará 1 programación' : `se generarán ${json.total} programaciones`}</span></div>`
                : `<div class="t-veredicto t-veredicto-error" role="alert">
                        <div class="t-veredicto-cabecera"><i class="bi bi-x-octagon-fill"></i><strong>No se puede guardar todavía</strong>
                            <span class="t-veredicto-contador">${conProblemas ? `${conProblemas} ${conProblemas === 1 ? 'grupo con problemas' : 'grupos con problemas'}` : 'revise los datos'}</span></div>
                        ${estructurales.length ? `<ul class="t-problemas">${estructurales.map((p, k) => tarjetaDeProblema(p, k, false, false)).join('')}</ul>` : ''}
                   </div>`;
            panel.innerHTML += `<h6 class="t-seccion-titulo mt-3 mb-0"><i class="bi bi-clipboard-check"></i> Resultado por grupo</h6><div class="av-resultado-grupos">${bloques.join('')}</div>`;
        };

        // «Detalles del grupo»: su personal y el historial de cambios (individuales y masivos).
        actualizar();
    }

    /* ------------------------------------------------------------------ listado de programaciones */

    const textoPlano = (valor, modo, formato) => (modo === 'display' ? formato(valor) : String(valor ?? ''));

    Tads.Modulos.programaciones = {
        init: function (contenedor) {
            const urlRecurso = contenedor.dataset.urlRecurso;
            const campo = (id) => document.getElementById(id);
            const valoresIniciales = { desde: campo('filtroDesde').value, hasta: campo('filtroHasta').value };
            const filtros = () => ({
                desde: campo('filtroDesde').value, hasta: campo('filtroHasta').value,
                zona_id: campo('filtroZona').value, turno_id: campo('filtroTurno').value,
                grupo_id: campo('filtroGrupo').value, persona_id: campo('filtroPersona').value, estado: campo('filtroEstado').value,
            });

            const gestor = new Tads.Gestor({
                entidad: 'programación',
                femenino: true,
                buscar: 'Buscar en las programaciones...',
                icono: 'bi-clock-history',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                porPagina: 15,
                filtros,
                alCargarListado: (json) => {
                    campo('avisoTruncado').hidden = !json.truncado;
                    // El botón de quitar solo aparece si el listado muestra programaciones sobre un feriado.
                    campo('botonQuitarFeriados').hidden = !json.data.some((f) => f.feriado && f.estado !== 'finalizado');
                },
                alCargarFormulario: activarFormularioDeProgramacion,
                exportar: { contenedor: '#botonesExportar', titulo: 'Programaciones', columnas: [0, 1, 2, 3, 4, 5, 6, 7] },
                columnas: [
                    { data: 'fecha', className: 'text-nowrap', render: Tads.ordenFecha },
                    { data: 'estado', render: (v, modo, fila) => {
                        if (modo !== 'display') return fila.estado_texto + (fila.feriado ? ` (feriado: ${fila.feriado})` : '') + (fila.sin_asistencia?.length ? ' (sin asistencia)' : '');

                        return Tads.insignia(fila.estado_texto, v === 'finalizado' ? 'verde' : (v === 'reprogramado' ? 'ambar' : 'azul'))
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
                const boton = (clase, icono, titulo, atributos) => `<button type="button" class="btn t-boton-icono ${clase}" ${atributos} data-bs-toggle="tooltip" title="${titulo}"><i class="bi ${icono}"></i></button>`;
                const grupo = fila.grupo_id ? boton('t-info', 'bi-people-fill', 'Detalles del grupo', `data-detalle-grupo="${fila.grupo_id}"`) : '';

                if (fila.estado === 'finalizado') {
                    return Tads.acciones(boton('t-info', 'bi-eye-fill', 'Ver detalle', `data-historial="${fila.id}" data-titulo="${Tads.escapar(quien)}"`), grupo);
                }

                const finalizar = fila.puede_finalizar
                    ? boton('t-exito', 'bi-check-circle-fill', 'Finalizar programación', `data-finalizar="${fila.id}" data-nombre="${Tads.escapar(quien)}" data-sin-asistencia="${Tads.escapar((fila.sin_asistencia ?? []).join(', '))}"`)
                    : Tads.botonBloqueado('bi-check-circle-fill', 't-exito', 'Solo se puede finalizar desde el día programado');

                return Tads.acciones(
                    fila.puede_modificar ? Tads.botonEditar(fila.id) : Tads.botonBloqueado('bi-pencil-square', 't-editar', 'Ya pasó su fecha: solo se puede finalizar o eliminar'),
                    Tads.botonEliminar(fila.id, quien),
                    boton('t-info', 'bi-clock-history', 'Ver historial de cambios', `data-historial="${fila.id}" data-titulo="${Tads.escapar(quien)}"`),
                    grupo,
                    finalizar,
                );
            }

            document.getElementById('tabla').addEventListener('click', async (evento) => {
                const historial = evento.target.closest('[data-historial]');
                const finalizar = evento.target.closest('[data-finalizar]');
                const detalleGrupo = evento.target.closest('[data-detalle-grupo]');

                if (detalleGrupo) {
                    bootstrap.Tooltip.getInstance(detalleGrupo)?.hide();
                    return gestor.abrirFormulario(`${contenedor.dataset.urlGrupo}/${detalleGrupo.dataset.detalleGrupo}/detalle`, '<i class="bi bi-people-fill me-2"></i>Detalles del grupo');
                }

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

            /* ---- Resumen por grupo: la vista de entrada, una tarjeta por grupo en lugar de una fila por día ---- */

            const fecha = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            const lista = (valores, vacio = '—') => Tads.escapar(valores.length ? valores.join(', ') : vacio);
            // Uno solo: el nombre. Varios (hubo un reemplazo): uno por línea con sus días, para que no parezcan dos a la vez.
            const conDias = (items) => (items.length === 1
                ? Tads.escapar(items[0].nombre)
                : items.map((i) => `<span class="d-block">${Tads.escapar(i.nombre)} <small class="text-muted">· ${i.dias} ${i.dias === 1 ? 'día' : 'días'}</small></span>`).join(''));
            let vista = 'detalle';
            // Grupo que puso «Ver días» (no el usuario): al volver al resumen se quita, para ver otra vez todos los grupos.
            let grupoDeFicha = null;
            try { vista = localStorage.getItem('programaciones.vista') === 'resumen' ? 'resumen' : 'detalle'; } catch (e) { /* sin almacenamiento */ }

            async function cargarResumen() {
                const destino = campo('resumenGrupos');
                destino.innerHTML = '<div class="t-fichas-cargando"><span class="spinner-border spinner-border-sm me-2"></span>Cargando…</div>';

                const { ok, estado, json } = await Tads.peticion(`${contenedor.dataset.urlListado}?${new URLSearchParams({ ...filtros(), vista: 'resumen' })}`);
                if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo cargar el resumen');

                const grupos = json.data;
                const total = grupos.reduce((suma, g) => suma + g.total, 0);
                campo('textoVista').textContent = vista === 'resumen'
                    ? `${grupos.length} ${grupos.length === 1 ? 'grupo' : 'grupos'} · ${total} ${total === 1 ? 'día programado' : 'días programados'} en el rango`
                    : '';

                if (!grupos.length) {
                    destino.innerHTML = `
                        <div class="t-tarjeta"><div class="t-vacio">
                            <i class="bi bi-calendar-x"></i>
                            <h6>No hay programaciones con estos filtros</h6>
                            <p>Pruebe con «Próximos 3 meses», pulse «Limpiar» o cree una con «Nueva programación».</p>
                        </div></div>`;
                    return;
                }

                destino.innerHTML = grupos.map((g) => `
                    <article class="t-ficha-grupo">
                        <header>
                            <div>
                                <h3>${Tads.escapar(g.grupo)}</h3>
                                <small>${lista(g.zonas)} · ${lista(g.turnos)}</small>
                            </div>
                            ${g.atrasadas ? Tads.insignia(`${g.atrasadas} por finalizar`, 'ambar') : ''}
                        </header>
                        <dl>
                            <div><dt>Fechas</dt><dd>${Tads.escapar(g.desde)} → ${Tads.escapar(g.hasta)}</dd></div>
                            <div><dt>Próximo día</dt><dd>${g.proxima ? Tads.escapar(g.proxima) : '<span class="text-muted">Ninguno pendiente</span>'}</dd></div>
                            <div><dt>${g.conductores.length > 1 ? 'Conductor (por días)' : 'Conductor'}</dt><dd>${conDias(g.conductores)}</dd></div>
                            <div><dt>${g.placas.length > 1 ? 'Vehículo (por días)' : 'Vehículo'}</dt><dd>${conDias(g.placas)}</dd></div>
                        </dl>
                        <footer>
                            <span class="t-ficha-cuenta"><strong>${g.total}</strong> ${g.total === 1 ? 'día' : 'días'}
                                <small>${g.programadas} programado${g.programadas === 1 ? '' : 's'} · ${g.finalizadas} finalizado${g.finalizadas === 1 ? '' : 's'}</small></span>
                            <span class="d-flex gap-1">
                                ${g.grupo_id && g.grupo_id !== 'sin' ? `<button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-detalle-grupo="${Tads.escapar(String(g.grupo_id))}"><i class="bi bi-people-fill me-1"></i> Detalles</button>` : ''}
                                <button type="button" class="btn t-boton btn-sm" data-ver-dias="${Tads.escapar(String(g.grupo_id))}"><i class="bi bi-list-ul me-1"></i> Ver días</button>
                            </span>
                        </footer>
                    </article>`).join('');
            }

            function mostrarVista(nueva) {
                vista = nueva;
                try { localStorage.setItem('programaciones.vista', vista); } catch (e) { /* sin almacenamiento */ }

                contenedor.querySelectorAll('[data-vista]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.vista === vista)));
                campo('panelResumen').hidden = vista !== 'resumen';
                campo('panelDetalle').hidden = vista !== 'detalle';

                if (vista === 'detalle') {
                    campo('textoVista').innerHTML = grupoDeFicha
                        ? `Mostrando solo los días de <strong>${Tads.escapar(grupoDeFicha)}</strong>. <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-volver-resumen><i class="bi bi-arrow-left me-1"></i>Volver a todos los grupos</button>`
                        : 'Cada fila es un día. Use el buscador o los filtros para acotar.';
                    gestor.tabla.columns.adjust();
                    gestor.recargar();
                } else {
                    if (grupoDeFicha) {
                        campo('filtroGrupo').value = '';
                        grupoDeFicha = null;
                    }
                    cargarResumen();
                }
            }

            const recargarTodo = () => (vista === 'resumen' ? cargarResumen() : gestor.recargar());

            contenedor.querySelectorAll('[data-vista]').forEach((b) => b.addEventListener('click', () => mostrarVista(b.dataset.vista)));

            // "Ver días" de una tarjeta: el detalle filtrado a ese grupo.
            campo('resumenGrupos').addEventListener('click', async (evento) => {
                const detalle = evento.target.closest('[data-detalle-grupo]');

                if (detalle) {
                    const { ok, estado, json, texto } = await Tads.peticion(`${contenedor.dataset.urlGrupo}/${detalle.dataset.detalleGrupo}/detalle`, { esperaJson: false });
                    if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo abrir el detalle del grupo');

                    return Swal.fire({ title: 'Detalles del grupo', html: `<div class="text-start">${texto}</div>`, width: 1000, showConfirmButton: false, showCloseButton: true });
                }

                const boton = evento.target.closest('[data-ver-dias]');
                if (!boton) return;
                campo('filtroGrupo').value = boton.dataset.verDias;
                grupoDeFicha = boton.closest('.t-ficha-grupo').querySelector('h3').textContent;
                mostrarVista('detalle');
            });
            campo('textoVista').addEventListener('click', (evento) => {
                if (evento.target.closest('[data-volver-resumen]')) mostrarVista('resumen');
            });

            // Atajos de fechas: un clic en vez de escribir dos fechas.
            const atajos = {
                hoy: () => { const h = new Date(); return [h, h]; },
                semana: () => { const h = new Date(); const lunes = new Date(h); lunes.setDate(h.getDate() - ((h.getDay() + 6) % 7)); const domingo = new Date(lunes); domingo.setDate(lunes.getDate() + 6); return [lunes, domingo]; },
                mes: () => { const h = new Date(); return [new Date(h.getFullYear(), h.getMonth(), 1), new Date(h.getFullYear(), h.getMonth() + 1, 0)]; },
                tres: () => { const h = new Date(); return [new Date(h.getFullYear(), h.getMonth(), 1), new Date(h.getFullYear(), h.getMonth() + 3, 0)]; },
            };
            const marcarAtajo = (clave) => contenedor.querySelectorAll('[data-atajo]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.atajo === clave)));

            contenedor.querySelectorAll('[data-atajo]').forEach((b) => b.addEventListener('click', () => {
                const [desde, hasta] = atajos[b.dataset.atajo]();
                campo('filtroDesde').value = fecha(desde);
                campo('filtroHasta').value = fecha(hasta);
                marcarAtajo(b.dataset.atajo);
                recargarTodo();
            }));
            ['filtroDesde', 'filtroHasta'].forEach((id) => campo(id).addEventListener('input', () => marcarAtajo(null)));

            // Las listas desplegables filtran al momento; las fechas, con «Filtrar» (se escriben en varios pasos).
            ['filtroGrupo', 'filtroPersona', 'filtroEstado', 'filtroZona', 'filtroTurno'].forEach((id) => campo(id).addEventListener('change', recargarTodo));
            // Si el usuario elige el grupo a mano, el filtro pasa a ser suyo y se respeta al volver al resumen.
            campo('filtroGrupo').addEventListener('change', () => {
                grupoDeFicha = null;
                if (vista === 'detalle') campo('textoVista').textContent = 'Cada fila es un día. Use el buscador o los filtros para acotar.';
            });

            campo('botonFiltrar').addEventListener('click', recargarTodo);
            campo('botonLimpiar').addEventListener('click', () => {
                campo('filtroDesde').value = valoresIniciales.desde;
                campo('filtroHasta').value = valoresIniciales.hasta;
                ['filtroZona', 'filtroTurno', 'filtroGrupo', 'filtroPersona', 'filtroEstado'].forEach((id) => { campo(id).value = ''; });
                marcarAtajo('tres');
                recargarTodo();
            });

            // Lo que cambia los datos (guardar, eliminar, finalizar, reemplazar) también debe refrescar el resumen.
            const recargarTabla = gestor.recargar.bind(gestor);
            gestor.recargar = () => { recargarTabla(); if (vista === 'resumen') cargarResumen(); };

            mostrarVista(vista);
        },
    };

    Tads.activarFormularioDeProgramacion = activarFormularioDeProgramacion;

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
