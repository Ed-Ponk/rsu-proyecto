/**
 * Módulo de Zonas (Tads)
 *
 * Zonas de recolección con mapa (Leaflet + OpenStreetMap, sin costo ni clave):
 *   - Formulario: combos en cascada departamento/provincia/distrito, buscador de direcciones del distrito,
 *     coordenadas editables y polígono dibujado con Leaflet.draw (ambos siempre sincronizados)
 *   - Ventana "Mapa de la zona": datos, área y tabla de coordenadas del perímetro
 *   - Explorador: todas las zonas en un mapa, con filtros por ubicación y estadísticas
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    const MINIMO_PUNTOS = 3;
    const COLORES_ZONA = ['#2563eb', '#16a34a', '#dc2626', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#65a30d'];

    /* ------------------------------------------------------------------ Leaflet */

    let leafletListo = false;
    let puntoGrande = null;   // ícono de los puntos del perímetro (se crea al preparar Leaflet)

    /** Ajustes que se hacen una sola vez: ruta de los íconos, textos en español y el área legible del dibujo. */
    function prepararLeaflet() {
        if (leafletListo) return;
        leafletListo = true;

        const css = document.querySelector('link[href*="vendor/leaflet/leaflet.css"]');
        const base = css ? css.href.replace('leaflet.css', 'images/') : '/vendor/leaflet/images/';
        L.Icon.Default.imagePath = base;
        L.Icon.Default.prototype.options.imagePath = base;

        if (L.GeometryUtil) {
            // La versión 1.0.4 de Leaflet.draw falla al mostrar el área mientras se dibuja: se reemplaza esta función.
            L.GeometryUtil.readableArea = (area) => (area >= 1e6
                ? `${(area / 1e6).toFixed(2)} km²`
                : (area >= 1e4 ? `${(area / 1e4).toFixed(2)} ha` : `${Math.round(area)} m²`));
        }

        // Puntos de dibujo y edición más grandes y redondos: más fáciles de ver y de arrastrar.
        puntoGrande = new L.DivIcon({ iconSize: new L.Point(16, 16), className: 'leaflet-div-icon leaflet-editing-icon t-punto-edicion' });

        if (L.drawLocal) {
            const d = L.drawLocal;
            d.draw.toolbar.actions = { title: 'Cancelar el dibujo', text: 'Cancelar' };
            d.draw.toolbar.finish = { title: 'Terminar el dibujo', text: 'Terminar' };
            d.draw.toolbar.undo = { title: 'Borrar el último punto', text: 'Borrar último punto' };
            d.draw.toolbar.buttons.polygon = 'Dibujar el perímetro de la zona';
            d.draw.handlers.polygon.tooltip = {
                start: 'Haga clic para empezar a dibujar el perímetro.',
                cont: 'Haga clic para continuar dibujando.',
                end: 'Haga clic en el primer punto para cerrar la figura.',
            };
            d.edit.toolbar.actions.save = { title: 'Guardar los cambios', text: 'Guardar' };
            d.edit.toolbar.actions.cancel = { title: 'Cancelar la edición', text: 'Cancelar' };
            d.edit.toolbar.actions.clearAll = { title: 'Quitar el perímetro', text: 'Quitar' };
            d.edit.toolbar.buttons.edit = 'Editar el perímetro';
            d.edit.toolbar.buttons.editDisabled = 'No hay perímetro para editar';
            d.edit.toolbar.buttons.remove = 'Quitar el perímetro';
            d.edit.toolbar.buttons.removeDisabled = 'No hay perímetro para quitar';
            d.edit.handlers.edit.tooltip = { text: 'Arrastre los puntos para ajustar el perímetro.', subtext: 'Pulse Cancelar para deshacer.' };
            d.edit.handlers.remove.tooltip = { text: 'Haga clic en el perímetro para quitarlo.' };
        }
    }

    function crearMapa(elemento, centro, zoom) {
        prepararLeaflet();
        const mapa = L.map(elemento).setView(centro, zoom);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        }).addTo(mapa);
        return mapa;
    }

    /** Los mapas dentro de una ventana emergente miden mal hasta que termina la animación de apertura. */
    function ajustarAlMostrar(ventana, mapa, alAjustar) {
        const ajustar = () => { mapa.invalidateSize(); alAjustar?.(); };
        ventana.addEventListener('shown.bs.modal', ajustar, { once: true });
        setTimeout(ajustar, 350);
    }

    const estiloZona = (activa, color) => ({
        color: activa ? (color ?? '#16a34a') : '#6b7280',
        weight: 2,
        fillColor: activa ? (color ?? '#16a34a') : '#9ca3af',
        fillOpacity: 0.28,
    });

    const numero = (v) => Number(v).toFixed(6);

    function centroDe(ubigeo, dep, prov, dist) {
        return ubigeo?.[dep]?.[prov]?.[dist] ?? null;
    }

    /** Llena un <select> con una lista de textos. */
    function llenarCombo(select, opciones, elegido, vacio) {
        select.innerHTML = (vacio ? `<option value="">${Tads.escapar(vacio)}</option>` : '')
            + opciones.map((o) => `<option value="${Tads.escapar(o)}">${Tads.escapar(o)}</option>`).join('');
        if (elegido && opciones.includes(elegido)) select.value = elegido;
    }

    /** Tres combos encadenados: al cambiar el departamento se recargan las provincias, y así hasta el distrito. */
    function encadenarCombos(ubigeo, dep, prov, dist, actual = {}, { conTodos = false, alCambiar } = {}) {
        const todos = conTodos ? 'Todos' : null;

        function cargarProvincias(elegida) {
            llenarCombo(prov, Object.keys(ubigeo[dep.value] ?? {}), elegida, todos);
            cargarDistritos();
        }

        function cargarDistritos(elegido) {
            llenarCombo(dist, Object.keys(ubigeo[dep.value]?.[prov.value] ?? {}), elegido, todos);
        }

        llenarCombo(dep, Object.keys(ubigeo), actual.department);
        llenarCombo(prov, Object.keys(ubigeo[dep.value] ?? {}), actual.province, todos);
        llenarCombo(dist, Object.keys(ubigeo[dep.value]?.[prov.value] ?? {}), actual.district, todos);

        dep.addEventListener('change', () => { cargarProvincias(); alCambiar?.(); });
        prov.addEventListener('change', () => { cargarDistritos(); alCambiar?.(); });
        dist.addEventListener('change', () => alCambiar?.());
    }

    /* ------------------------------------------------------------------ Formulario de zona */

    function activarFormularioZona(cuerpo, urlMapa) {
        const ubicacion = cuerpo.querySelector('#zonaUbicacion');
        const editor = cuerpo.querySelector('#zonaEditor');
        if (!ubicacion || !editor) return;

        const ubigeo = JSON.parse(ubicacion.dataset.ubigeo);
        const actual = JSON.parse(ubicacion.dataset.actual);
        const verticesIniciales = JSON.parse(editor.dataset.vertices);
        const idZona = editor.dataset.zonaId;
        const urlBuscarDireccion = editor.dataset.urlBuscarDireccion;
        const mapaInicial = JSON.parse(editor.dataset.mapaInicial);

        const dep = cuerpo.querySelector('#zonaDepartamento');
        const prov = cuerpo.querySelector('#zonaProvincia');
        const dist = cuerpo.querySelector('#zonaDistrito');
        const contenedorFilas = cuerpo.querySelector('#zonaFilas');
        const conteo = cuerpo.querySelector('#zonaConteo');
        const ventana = cuerpo.closest('.modal');

        const mapa = crearMapa(cuerpo.querySelector('#zonaMapa'), [mapaInicial.lat, mapaInicial.lng], mapaInicial.zoom);
        const referencia = L.featureGroup().addTo(mapa);   // las demás zonas, solo para orientarse
        const dibujo = L.featureGroup().addTo(mapa);       // el perímetro de esta zona (editable)
        let marcadorDistrito = null;
        let marcadorBusqueda = null;
        let refrescarBarra = () => {};   // se completa al armar la barra de herramientas

        ventana.addEventListener('hidden.bs.modal', () => mapa.remove(), { once: true });

        /* ---------- ubicación ---------- */
        function centrarEnDistrito() {
            const centro = centroDe(ubigeo, dep.value, prov.value, dist.value);
            if (!centro) return;

            mapa.setView(centro, 14);
            marcadorDistrito?.remove();
            marcadorDistrito = marcarCentroDelDistrito(centro);
        }

        encadenarCombos(ubigeo, dep, prov, dist, actual, { alCambiar: centrarEnDistrito });

        /** Centro del distrito: punto rojo bien visible con su nombre; el pin azul queda reservado al resultado de la búsqueda. */
        function marcarCentroDelDistrito(centro) {
            const marcador = L.circleMarker(centro, { radius: 10, color: '#ffffff', weight: 3, fillColor: '#dc2626', fillOpacity: 1 })
                .addTo(mapa)
                .bindTooltip(`Centro de ${Tads.escapar(dist.value)}`, { permanent: true, direction: 'top', offset: [0, -8], className: 't-etiqueta-centro' });
            return marcador;
        }

        /* ---------- filas de coordenadas ---------- */
        const filas = () => [...contenedorFilas.querySelectorAll('.t-coord-fila')];

        function renumerar() {
            filas().forEach((fila, i) => {
                fila.querySelector('[data-lat]').name = `coordinates[${i}][latitude]`;
                fila.querySelector('[data-lng]').name = `coordinates[${i}][longitude]`;
            });
            const n = filas().length;
            conteo.textContent = `${n} coordenada${n === 1 ? '' : 's'}.`;
        }

        function agregarFila(lat = '', lng = '') {
            const fila = document.createElement('div');
            fila.className = 't-coord-fila';
            fila.innerHTML = `
                <input type="number" step="any" min="-90" max="90" class="form-control" data-lat placeholder="Latitud" required value="${lat}">
                <input type="number" step="any" min="-180" max="180" class="form-control" data-lng placeholder="Longitud" required value="${lng}">
                <button type="button" class="btn t-boton-icono t-eliminar" data-quitar title="Quitar coordenada"><i class="bi bi-trash3-fill"></i></button>`;
            contenedorFilas.appendChild(fila);
            renumerar();
            return fila;
        }

        function puntosDeLasFilas() {
            return filas()
                .map((f) => [parseFloat(f.querySelector('[data-lat]').value), parseFloat(f.querySelector('[data-lng]').value)])
                .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180);
        }

        /** Las filas mandan: se redibuja el polígono con lo que haya escrito (o una línea si aún faltan puntos). */
        function dibujarDesdeLasFilas({ ajustar = false } = {}) {
            dibujo.clearLayers();
            programarRevision();
            setTimeout(() => refrescarBarra());
            const puntos = puntosDeLasFilas();

            if (puntos.length >= MINIMO_PUNTOS) {
                const poligono = L.polygon(puntos, { color: '#2563eb', weight: 3, fillOpacity: 0.25 });
                dibujo.addLayer(poligono);
                if (ajustar) mapa.fitBounds(poligono.getBounds(), { padding: [30, 30], maxZoom: 17 });
            } else if (puntos.length) {
                puntos.forEach((p) => dibujo.addLayer(L.circleMarker(p, { radius: 5, color: '#2563eb' })));
                if (puntos.length > 1) dibujo.addLayer(L.polyline(puntos, { color: '#2563eb', dashArray: '6 6' }));
            }
        }

        /** El mapa manda: las filas se reescriben con los vértices del polígono dibujado o editado. */
        function llenarFilasDesde(capa) {
            programarRevision();
            setTimeout(() => refrescarBarra());
            contenedorFilas.innerHTML = '';
            const anillo = capa.getLatLngs()[0] ?? [];
            anillo.forEach((p) => agregarFila(numero(p.lat), numero(p.lng)));
            renumerar();
        }

        function limpiarTodo() {
            programarRevision();
            setTimeout(() => refrescarBarra());
            contenedorFilas.innerHTML = '';
            dibujo.clearLayers();
            renumerar();
        }

        contenedorFilas.addEventListener('input', () => dibujarDesdeLasFilas());
        contenedorFilas.addEventListener('click', (evento) => {
            const quitar = evento.target.closest('[data-quitar]');
            if (!quitar) return;
            quitar.closest('.t-coord-fila').remove();
            renumerar();
            dibujarDesdeLasFilas();
        });
        cuerpo.querySelector('#zonaAgregar').addEventListener('click', () => agregarFila().querySelector('[data-lat]').focus());
        cuerpo.querySelector('#zonaLimpiar').addEventListener('click', limpiarTodo);

        /* ---------- revisión en vivo del perímetro ---------- */
        const aviso = cuerpo.querySelector('#zonaAviso');
        const urlVerificar = editor.dataset.urlVerificar;
        let esperaRevision = null;
        let turnoRevision = 0;

        function programarRevision() {
            clearTimeout(esperaRevision);
            esperaRevision = setTimeout(revisarPerimetro, 400);
        }

        async function revisarPerimetro() {
            // Se espera a que renumerar() haya puesto los nombres de los campos.
            const cuerpoPeticion = new FormData();
            cuerpoPeticion.append('zona', idZona);
            cuerpoPeticion.append('department', dep.value);
            filas().forEach((fila, i) => {
                const lat = fila.querySelector('[data-lat]').value;
                const lng = fila.querySelector('[data-lng]').value;
                if (lat === '' || lng === '') return;
                cuerpoPeticion.append(`coordinates[${i}][latitude]`, lat);
                cuerpoPeticion.append(`coordinates[${i}][longitude]`, lng);
            });

            const miTurno = ++turnoRevision;
            const { ok, json } = await Tads.peticion(urlVerificar, { metodo: 'POST', cuerpo: cuerpoPeticion });
            if (miTurno !== turnoRevision || !mapa._container) return;

            const problema = ok ? json.mensaje : null;
            aviso.hidden = !problema;
            aviso.innerHTML = problema ? `<i class="bi bi-exclamation-triangle-fill me-1"></i>${Tads.escapar(problema)}` : '';
            dibujo.eachLayer((capa) => capa.setStyle?.(problema
                ? { color: '#dc2626', fillColor: '#dc2626' }
                : { color: '#2563eb', fillColor: '#2563eb' }));
        }

        /* ---------- barra de herramientas del mapa ---------- */
        // En lugar de los íconos de Leaflet.draw se usa una barra con botones con texto y una guía del paso en curso.
        const barra = cuerpo.querySelector('#zonaBarra');
        const guia = cuerpo.querySelector('#zonaGuia');
        const accion = (nombre) => barra.querySelector(`[data-accion="${nombre}"]`);
        const poligonoActual = () => dibujo.getLayers().find((c) => c instanceof L.Polygon);
        const estiloPerimetro = { color: '#2563eb', weight: 3, fillOpacity: 0.25 };
        let modo = 'reposo';        // reposo | dibujando | editando
        let manejadorDibujo = null;
        let manejadorEdicion = null;

        const GUIAS = {
            sinPerimetro: 'Pulse «Dibujar perímetro» y haga clic en el mapa para colocar cada punto del contorno de la zona. También puede escribir las coordenadas en los campos de arriba.',
            conPerimetro: 'El perímetro está listo. Use «Editar puntos» para moverlo o «Borrar perímetro» para empezar de nuevo.',
            dibujando: 'Haga clic en el mapa para colocar cada punto. Con 3 o más puntos pulse «Terminar» (o haga clic en el primer punto).',
            editando: 'Arrastre los círculos blancos para mover los puntos; arrastre los de en medio de cada lado para agregar uno nuevo. Pulse «Guardar cambios» al terminar.',
        };

        refrescarBarra = function () {
            const hay = !!poligonoActual();
            barra.querySelectorAll('[data-grupo]').forEach((g) => { g.hidden = g.dataset.grupo !== modo; });
            accion('editar').disabled = !hay;
            accion('borrar').disabled = !hay;
            accion('dibujar').innerHTML = hay
                ? '<i class="bi bi-arrow-repeat me-1"></i> Dibujar de nuevo'
                : '<i class="bi bi-pentagon-fill me-1"></i> Dibujar perímetro';
            guia.innerHTML = `<i class="bi bi-lightbulb-fill me-1"></i>${modo === 'reposo' ? (hay ? GUIAS.conPerimetro : GUIAS.sinPerimetro) : GUIAS[modo]}`;
        };

        function cambiarModo(nuevoModo) {
            modo = nuevoModo;
            refrescarBarra();
        }

        function empezarDibujo() {
            manejadorDibujo = new L.Draw.Polygon(mapa, { allowIntersection: false, showArea: true, shapeOptions: estiloPerimetro, icon: puntoGrande, touchIcon: puntoGrande });
            manejadorDibujo.enable();
            cambiarModo('dibujando');
        }

        accion('dibujar').addEventListener('click', async () => {
            if (poligonoActual()) {
                const decision = await Swal.fire({
                    title: '¿Dibujar de nuevo?',
                    text: 'Se reemplazará el perímetro actual por el que dibuje.',
                    icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, dibujar', cancelButtonText: 'Cancelar', reverseButtons: true,
                });
                if (!decision.isConfirmed) return;
            }
            empezarDibujo();
        });

        accion('terminar').addEventListener('click', () => {
            if ((manejadorDibujo?._markers?.length ?? 0) < MINIMO_PUNTOS) return Tads.aviso(`Coloque al menos ${MINIMO_PUNTOS} puntos en el mapa.`, 'warning');
            manejadorDibujo.completeShape();
        });
        accion('deshacer').addEventListener('click', () => manejadorDibujo?.deleteLastVertex());
        accion('cancelarDibujo').addEventListener('click', () => manejadorDibujo?.disable());

        accion('editar').addEventListener('click', () => {
            if (!poligonoActual()) return;
            manejadorEdicion = new L.EditToolbar.Edit(mapa, { featureGroup: dibujo, poly: { allowIntersection: false, icon: puntoGrande, touchIcon: puntoGrande } });
            manejadorEdicion.enable();
            cambiarModo('editando');
        });
        accion('guardarEdicion').addEventListener('click', () => { manejadorEdicion?.save(); manejadorEdicion?.disable(); });
        accion('cancelarEdicion').addEventListener('click', () => { manejadorEdicion?.revertLayers(); manejadorEdicion?.disable(); });

        accion('borrar').addEventListener('click', async () => {
            const decision = await Swal.fire({
                title: '¿Borrar el perímetro?',
                text: 'Se quitarán el dibujo y todas las coordenadas para empezar de nuevo.',
                icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Sí, borrar', cancelButtonText: 'Cancelar', reverseButtons: true,
            });
            if (decision.isConfirmed) limpiarTodo();
        });

        refrescarBarra();

        mapa.on(L.Draw.Event.CREATED, (e) => {
            dibujo.clearLayers();              // una zona tiene un solo perímetro
            dibujo.addLayer(e.layer);
            llenarFilasDesde(e.layer);
        });
        mapa.on(L.Draw.Event.DRAWSTOP, () => cambiarModo('reposo'));
        mapa.on(L.Draw.Event.EDITSTOP, () => cambiarModo('reposo'));
        mapa.on(L.Draw.Event.EDITED, () => { const p = poligonoActual(); if (p) llenarFilasDesde(p); });
        mapa.on(L.Draw.Event.EDITVERTEX, () => { const p = poligonoActual(); if (p) llenarFilasDesde(p); });


        /* ---------- zonas de referencia ---------- */
        (async () => {
            const { ok, json } = await Tads.peticion(urlMapa);
            if (!ok || !mapa._container) return;

            json.data.filter((z) => String(z.id) !== String(idZona) && z.vertices.length >= MINIMO_PUNTOS).forEach((z) => {
                L.polygon(z.vertices, { color: '#ef4444', weight: 2, fillOpacity: 0.12 })
                    .bindTooltip(Tads.escapar(z.nombre), { sticky: true })
                    .addTo(referencia);
            });
        })();

        /* ---------- buscador de direcciones (Nominatim de OpenStreetMap) ---------- */
        const campoDireccion = cuerpo.querySelector('#zonaDireccion');
        const listaDirecciones = cuerpo.querySelector('#zonaDirecciones');
        let temporizador = null;
        let turno = 0;

        const pausa = (ms) => new Promise((resolver) => setTimeout(resolver, ms));

        /**
         * Una consulta al buscador de OpenStreetMap. Primero directo desde el navegador y, si no se puede
         * (bloqueo de red, antivirus, extensión...), a través del servidor del sistema. Devuelve la lista de lugares.
         */
        async function consultarLugares(parametros) {
            try {
                const respuesta = await fetch(`https://nominatim.openstreetmap.org/search?${parametros}`);
                if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);
                return await respuesta.json();
            } catch (errorDirecto) {
                const { ok, estado, json } = await Tads.peticion(`${urlBuscarDireccion}?${parametros}`);
                if (!ok) {
                    const motivo = errorDirecto.message === 'Failed to fetch' ? 'el navegador no pudo conectarse' : errorDirecto.message;
                    throw new Error(`No se pudo consultar el buscador (${motivo}; ${json?.mensaje ?? `el servidor respondió ${estado}`}).`);
                }
                return json.data;
            }
        }

        /**
         * El buscador devuelve también lugares de distritos vecinos (la caja de búsqueda es un rectángulo).
         * Se descarta todo resultado cuya dirección nombre otro distrito o provincia del catálogo: así, con
         * «Lambayeque» elegido no aparecen calles de José Leonardo Ortiz ni de Chiclayo.
         */
        function delDistritoElegido(lugares) {
            const ajenos = new Set();
            Object.entries(ubigeo).forEach(([, provincias]) => Object.entries(provincias).forEach(([provincia, distritos]) => {
                ajenos.add(provincia);
                Object.keys(distritos).forEach((distrito) => ajenos.add(distrito));
            }));
            [dist.value, prov.value, dep.value].forEach((propio) => ajenos.delete(propio));

            return lugares.filter((l) => !l.display_name.split(',').some((parte) => ajenos.has(parte.trim())));
        }

        /** Texto de una calle como patrón que ignora tildes, mayúsculas y el tipo de vía ("Calle", "Jr.", "Av."). */
        function patronDeCalle(texto) {
            const limpio = texto.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
                .replace(/^(calle|jiron|jr\.?|avenida|av\.?|pasaje|psje\.?|pje\.?)\s+/, '')
                .replace(/[^a-z0-9ñ ]/g, '')
                .trim();
            const clases = { a: 'aáàâä', e: 'eéèêë', i: 'iíìîï', o: 'oóòôö', u: 'uúùûü' };
            return limpio.replace(/[aeiou]/g, (v) => `[${clases[v]}]`).replace(/\s+/g, ' ');
        }

        /**
         * Cruce real de dos calles: busca en OpenStreetMap (Overpass) los puntos que pertenecen a las dos vías.
         * Devuelve lugares con la misma forma que el buscador de direcciones, los más cercanos al centro primero.
         */
        async function buscarCruces(calleA, calleB, centro, radio) {
            const [s, o, n, e] = [centro[0] - radio, centro[1] - radio, centro[0] + radio, centro[1] + radio].map((v) => v.toFixed(5));
            const consulta = `[out:json][timeout:20];
                way["highway"]["name"~"${patronDeCalle(calleA)}",i](${s},${o},${n},${e})->.a;
                way["highway"]["name"~"${patronDeCalle(calleB)}",i](${s},${o},${n},${e})->.b;
                node(w.a)(w.b);
                out body;`;

            const respuesta = await fetch('https://overpass-api.de/api/interpreter', { method: 'POST', body: new URLSearchParams({ data: consulta }) });
            if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);
            const { elements } = await respuesta.json();

            const vistos = new Set();
            return elements
                .map((nodo) => ({ lat: nodo.lat, lon: nodo.lon, km: distanciaEntre(centro, [nodo.lat, nodo.lon]) }))
                .sort((x, y) => x.km - y.km)
                .filter((p) => { const clave = `${p.lat.toFixed(4)},${p.lon.toFixed(4)}`; return !vistos.has(clave) && vistos.add(clave); })
                .slice(0, 5)
                .map((p) => ({
                    display_name: `Cruce de ${calleA} y ${calleB} (a ${p.km.toFixed(1)} km del centro de ${dist.value})`,
                    lat: p.lat, lon: p.lon, esCruce: true,
                }));
        }

        /** Distancia aproximada en km entre dos puntos [lat, lng]. */
        function distanciaEntre([lat1, lng1], [lat2, lng2]) {
            const rad = (g) => (g * Math.PI) / 180;
            const a = Math.sin(rad(lat2 - lat1) / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
            return 6371 * 2 * Math.asin(Math.sqrt(a));
        }

        async function buscarDireccion() {
            const texto = campoDireccion.value.trim();
            if (texto.length < 3) { listaDirecciones.hidden = true; return; }

            const centro = centroDe(ubigeo, dep.value, prov.value, dist.value) ?? [mapaInicial.lat, mapaInicial.lng];
            const radio = 0.06;   // ~7 km alrededor del centro del distrito (luego se filtra por distrito)
            const caja = [centro[1] - radio, centro[0] + radio, centro[1] + radio, centro[0] - radio].join(',');
            const base = { format: 'jsonv2', limit: '15', countrycodes: 'pe', viewbox: caja };

            const miTurno = ++turno;
            listaDirecciones.hidden = false;
            listaDirecciones.innerHTML = '<li class="text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Buscando...</li>';

            // El buscador no entiende esquinas ("Huacas y San Martín"): si no hay resultados se prueba con cada calle.
            const intentos = [{ q: texto, bounded: '1' }];
            const calles = texto.split(/\s+(?:y|con|e|esquina|&)\s+/i).map((c) => c.trim()).filter((c) => c.length >= 3);
            if (calles.length > 1) calles.forEach((calle) => intentos.push({ q: calle, bounded: '1' }));
            intentos.push({ q: `${texto}, ${dist.value}`, bounded: '0' });

            let lugares = [];

            // "Huáscar y San Martín": primero se intenta ubicar el punto exacto donde se cruzan.
            if (calles.length === 2) {
                try {
                    lugares = await buscarCruces(calles[0], calles[1], centro, radio);
                } catch (errorCruce) {
                    lugares = [];   // sin servicio de cruces: se sigue con la búsqueda normal
                }
                if (miTurno !== turno) return;
            }

            try {
                for (let i = 0; i < intentos.length && !lugares.length; i++) {
                    if (i > 0) await pausa(1100);                // la política del servicio es 1 consulta por segundo
                    if (miTurno !== turno) return;
                    lugares = delDistritoElegido(await consultarLugares(new URLSearchParams({ ...base, ...intentos[i] }))).slice(0, 6);
                }
            } catch (error) {
                if (miTurno === turno) listaDirecciones.innerHTML = `<li class="text-danger">${Tads.escapar(error.message)} Revise su conexión a internet o intente de nuevo en unos segundos.</li>`;
                return;
            }

            if (miTurno !== turno) return;
            listaDirecciones.innerHTML = lugares.length
                ? lugares.map((l, i) => `<li data-indice="${i}" role="option"><i class="bi bi-geo-alt me-1"></i>${Tads.escapar(l.display_name)}</li>`).join('')
                : `<li class="text-muted">Sin resultados. Pruebe con el nombre de una calle, una esquina («Huáscar y San Martín») o un lugar conocido de ${Tads.escapar(dist.value)} (ej. «Plaza de Armas»).</li>`;
            listaDirecciones._lugares = lugares;
        }

        campoDireccion.addEventListener('input', () => { clearTimeout(temporizador); temporizador = setTimeout(buscarDireccion, 500); });
        campoDireccion.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
        listaDirecciones.addEventListener('click', (evento) => {
            const item = evento.target.closest('[data-indice]');
            const lugar = item && listaDirecciones._lugares?.[item.dataset.indice];
            if (!lugar) return;

            const punto = [parseFloat(lugar.lat), parseFloat(lugar.lon)];
            marcadorBusqueda?.remove();
            marcadorBusqueda = L.marker(punto).addTo(mapa).bindPopup(Tads.escapar(lugar.display_name)).openPopup();
            mapa.setView(punto, lugar.esCruce ? 18 : 17);
            listaDirecciones.hidden = true;
        });

        /* ---------- estado inicial ---------- */
        verticesIniciales.forEach(([lat, lng]) => agregarFila(numero(lat), numero(lng)));
        renumerar();

        if (verticesIniciales.length) {
            dibujarDesdeLasFilas({ ajustar: true });
            const centro = centroDe(ubigeo, dep.value, prov.value, dist.value);
            if (centro) marcadorDistrito = marcarCentroDelDistrito(centro);
        } else {
            centrarEnDistrito();
        }

        ajustarAlMostrar(ventana, mapa, () => {
            if (poligonoActual()) mapa.fitBounds(poligonoActual().getBounds(), { padding: [30, 30], maxZoom: 17 });
        });
    }

    /* ------------------------------------------------------------------ Ventana "Mapa de la zona" */

    function crearVisorDeZona(urlRecurso) {
        const ventana = document.getElementById('ventanaZona');
        const campo = (id) => document.getElementById(id);
        let mapa = null;
        let capa = null;

        return async function abrir(id) {
            const { ok, estado, json } = await Tads.peticion(`${urlRecurso}/${id}`);
            if (!ok) return Tads.alertaDeError(estado, json, 'No se pudo cargar la zona');

            campo('zonaVerNombre').textContent = json.nombre;
            campo('zonaVerDistrito').textContent = `${json.distrito} · ${json.provincia}`;
            campo('zonaVerPuntos').textContent = json.puntos;
            campo('zonaVerResiduos').textContent = json.residuos !== null ? `${json.residuos} kg` : 'N/A';
            campo('zonaVerDepartamento').textContent = json.departamento;
            campo('zonaVerArea').textContent = `${json.area_km2} km²`;
            campo('zonaVerDescripcion').textContent = json.descripcion || 'Sin descripción.';
            campo('zonaVerCoordenadas').innerHTML = json.vertices
                .map(([lat, lng], i) => `<tr><td>${i + 1}</td><td>${numero(lat)}</td><td>${numero(lng)}</td></tr>`).join('');

            const instancia = bootstrap.Modal.getOrCreateInstance(ventana);

            const pintar = () => {
                if (!mapa) mapa = crearMapa(campo('zonaVerMapa'), [-6.7623, -79.8368], 14);
                capa?.remove();
                mapa.invalidateSize();

                const poligono = L.polygon(json.vertices, estiloZona(json.activa, '#2563eb'));
                const popup = `<strong>${Tads.escapar(json.nombre)}</strong><br><small>${Tads.escapar(json.distrito)}</small><br>`
                    + `<small>Residuos: ${json.residuos !== null ? `${json.residuos} kg` : 'N/A'}</small><br><small>${json.puntos} puntos</small>`;
                const marcador = L.marker(poligono.getBounds().getCenter()).bindPopup(popup);
                capa = L.featureGroup([poligono, marcador]).addTo(mapa);
                mapa.fitBounds(poligono.getBounds(), { padding: [30, 30], maxZoom: 17 });
                marcador.openPopup();
            };

            if (ventana.classList.contains('show')) pintar();
            else {
                ventana.addEventListener('shown.bs.modal', pintar, { once: true });
                instancia.show();
            }
        };
    }

    /* ------------------------------------------------------------------ Explorador */

    function crearExplorador({ urlMapa, ubigeo, inicial }) {
        const ventana = document.getElementById('ventanaExplorador');
        const campo = (id) => document.getElementById(id);
        const dep = campo('exploradorDepartamento');
        const prov = campo('exploradorProvincia');
        const dist = campo('exploradorDistrito');
        let mapa = null;
        let capa = null;

        async function cargar() {
            const parametros = new URLSearchParams();
            if (dep.value) parametros.set('department', dep.value);
            if (prov.value) parametros.set('province', prov.value);
            if (dist.value) parametros.set('district', dist.value);

            const { ok, estado, json } = await Tads.peticion(`${urlMapa}?${parametros}`);
            if (!ok) return Tads.alertaDeError(estado, json, 'No se pudieron cargar las zonas');

            campo('exploradorZonas').textContent = json.resumen.zonas;
            campo('exploradorActivas').textContent = json.resumen.activas;
            campo('exploradorPuntos').textContent = json.resumen.puntos;

            const lugar = [dist.value, prov.value, dep.value].filter(Boolean).join(', ') || 'Todas las ubicaciones';
            campo('exploradorTexto').innerHTML = `<i class="bi bi-info-circle-fill me-1"></i> ${json.resumen.zonas} zona(s) encontrada(s) en <strong>${Tads.escapar(lugar)}</strong>`;

            capa?.remove();
            capa = L.featureGroup().addTo(mapa);

            json.data.filter((z) => z.vertices.length >= MINIMO_PUNTOS).forEach((z, i) => {
                const color = z.activa ? COLORES_ZONA[i % COLORES_ZONA.length] : '#6b7280';
                const popup = `<strong>${Tads.escapar(z.nombre)}</strong><br>`
                    + `${z.activa ? Tads.insignia('Activa', 'verde') : Tads.insignia('Inactiva', 'gris')} ${Tads.insignia(`${z.puntos} puntos`, 'azul')}<br>`
                    + `<small>${Tads.escapar(z.descripcion ?? '')}</small>`;
                L.polygon(z.vertices, estiloZona(z.activa, color)).bindPopup(popup).bindTooltip(Tads.escapar(z.nombre), { sticky: true }).addTo(capa);
            });

            mapa.invalidateSize();
            if (capa.getLayers().length) {
                mapa.fitBounds(capa.getBounds(), { padding: [30, 30], maxZoom: 16 });
            } else {
                const centro = centroDe(ubigeo, dep.value, prov.value, dist.value) ?? [inicial.lat, inicial.lng];
                mapa.setView(centro, dist.value ? 14 : 11);
            }
        }

        encadenarCombos(ubigeo, dep, prov, dist, {}, { conTodos: true, alCambiar: cargar });

        return function abrir() {
            const instancia = bootstrap.Modal.getOrCreateInstance(ventana);
            const iniciar = () => {
                if (!mapa) mapa = crearMapa(campo('exploradorMapa'), [inicial.lat, inicial.lng], inicial.zoom);
                cargar();
            };

            if (ventana.classList.contains('show')) return iniciar();
            ventana.addEventListener('shown.bs.modal', iniciar, { once: true });
            instancia.show();
        };
    }

    /* ------------------------------------------------------------------ Listado */

    Tads.Modulos.zonas = {
        init: function (contenedor) {
            const urlRecurso = contenedor.dataset.urlRecurso;
            const urlMapa = contenedor.dataset.urlMapa;
            const ubigeo = JSON.parse(contenedor.dataset.ubigeo);
            const inicial = JSON.parse(contenedor.dataset.mapaInicial);

            const abrirVisor = crearVisorDeZona(urlRecurso);
            const abrirExplorador = crearExplorador({ urlMapa, ubigeo, inicial });

            const gestor = new Tads.Gestor({
                entidad: 'zona',
                femenino: true,
                icono: 'bi-geo-alt-fill',
                urlListado: contenedor.dataset.urlListado,
                urlRecurso,
                urlCrear: contenedor.dataset.urlCrear,
                orden: [[0, 'asc']],
                alCargarFormulario: (cuerpo) => activarFormularioZona(cuerpo, urlMapa),
                columnas: [
                    { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'distrito' },
                    { data: 'provincia' },
                    { data: 'departamento' },
                    { data: 'descripcion', defaultContent: '', render: (v) => v ? Tads.escapar(v) : '<span class="text-muted">—</span>' },
                    { data: 'puntos', render: (v) => Tads.insignia(`${v} puntos`, v >= MINIMO_PUNTOS ? 'azul' : 'rojo') },
                    { data: 'activa', render: (v) => v ? Tads.insignia('Activo', 'verde') : Tads.insignia('Inactivo', 'gris') },
                    { data: 'creado', render: Tads.celdaFecha },
                    { data: null, orderable: false, searchable: false, className: 'text-center text-nowrap',
                        render: (fila) => Tads.acciones(
                            Tads.botonEditar(fila.id),
                            Tads.botonEliminar(fila.id, fila.nombre),
                            `<button type="button" class="btn t-boton-icono t-info" data-mapa="${fila.id}" data-bs-toggle="tooltip" title="Ver perímetro en el mapa"><i class="bi bi-map-fill"></i></button>`,
                        ) },
                ],
            });

            contenedor._gestor = gestor;

            document.getElementById('tabla').addEventListener('click', (evento) => {
                const boton = evento.target.closest('[data-mapa]');
                if (boton) abrirVisor(boton.dataset.mapa);
            });

            document.getElementById('botonVerMapa')?.addEventListener('click', abrirExplorador);
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
