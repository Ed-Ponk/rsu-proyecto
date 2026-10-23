/**
 * Módulo de Vehículos (Tads)
 *
 * Administra el listado DataTables de flota, filtro en cascada marca->modelo
 * en el formulario modal y el gestor de galería multi-imagen con arrastre (drag-and-drop),
 * previsualización de blobs en memoria, marcado para eliminación y selección de foto de portada.
 */
(function (global) {
    'use strict';

    const Tads = (global.Tads = global.Tads || {});
    Tads.Modulos = Tads.Modulos || {};

    function iniciarGestorImagenes(contenedor, gestorVehiculos) {
        const MAX_BYTES = 2 * 1024 * 1024;
        const FORMATOS = ['image/jpeg', 'image/png', 'image/gif'];

        const estado = { vehiculoId: null, maximo: 12, guardadas: [], nuevas: [], perfil: null };

        const ventanaEl = document.getElementById('ventanaImagenes');
        if (!ventanaEl) return;

        const alerta = document.getElementById('imgAlerta');
        const zona = document.getElementById('zonaArrastre');
        const entrada = document.getElementById('archivosImagen');
        const nodo = (id) => document.getElementById(id);

        const urlBaseRecurso = contenedor.dataset.urlRecurso || '/admin/vehiculos';
        const urlImagenesBase = contenedor.dataset.urlImagenes || urlBaseRecurso;

        const mostrarAlerta = (lineas) => {
            if (!alerta) return;
            alerta.innerHTML = lineas.length
                ? '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + lineas.map(Tads.escapar).join('<br>')
                : '';
            alerta.classList.toggle('visible', lineas.length > 0);
        };

        const tarjeta = (imagen, esPerfil, pie, esNueva) => {
            const caja = document.createElement('div');
            caja.className = 't-imagen' + (esPerfil ? ' es-perfil' : '') + (imagen.quitada ? ' quitada' : '');
            caja.innerHTML =
                `<img src="${Tads.escapar(imagen.url)}" alt="Imagen">` +
                (esPerfil ? '<span class="t-marca-perfil"><i class="bi bi-star-fill"></i> Perfil</span>' : '') +
                '<button type="button" class="t-btn-imagen t-btn-perfil" data-orden="perfil" title="Usar como imagen de perfil"><i class="bi bi-star-fill"></i></button>' +
                `<button type="button" class="t-btn-imagen t-btn-quitar" data-orden="quitar" title="${imagen.quitada ? 'Restaurar' : 'Quitar'}"><i class="bi ${imagen.quitada ? 'bi-arrow-counterclockwise' : 'bi-x-lg'}"></i></button>` +
                `<div class="t-pie-imagen">${Tads.escapar(pie)}</div>`;
            caja._imagen = imagen;
            caja._esNueva = esNueva;
            return caja;
        };

        function dibujar() {
            const guardadas = nodo('listaGuardadas');
            const nuevas = nodo('listaNuevas');
            if (!guardadas || !nuevas) return;

            guardadas.innerHTML = '';
            nuevas.innerHTML = '';

            estado.guardadas.forEach((img) => {
                const esPerfil = estado.perfil?.tipo === 'existing' && estado.perfil.id === img.id;
                guardadas.appendChild(tarjeta(img, esPerfil, img.quitada ? 'Se eliminará al guardar' : 'Guardada', false));
            });

            estado.nuevas.forEach((img) => {
                const esPerfil = estado.perfil?.tipo === 'new' && estado.perfil.ref === img;
                nuevas.appendChild(tarjeta(img, esPerfil, `${img.archivo.name} · ${Math.round(img.archivo.size / 1024)} KB`, true));
            });

            if (nodo('contadorGuardadas')) nodo('contadorGuardadas').textContent = estado.guardadas.length;
            if (nodo('contadorNuevas')) nodo('contadorNuevas').textContent = estado.nuevas.length;
            if (nodo('bloqueNuevas')) nodo('bloqueNuevas').classList.toggle('d-none', estado.nuevas.length === 0);
            if (nodo('sinImagenes')) nodo('sinImagenes').classList.toggle('d-none', estado.guardadas.length > 0);
        }

        function asegurarPerfil() {
            if (estado.perfil) return;
            const primera = estado.guardadas.find((i) => !i.quitada);
            if (primera) estado.perfil = { tipo: 'existing', id: primera.id };
            else if (estado.nuevas.length) estado.perfil = { tipo: 'new', ref: estado.nuevas[0] };
        }

        function agregarArchivos(lista) {
            const problemas = [];
            const conservadas = estado.guardadas.filter((i) => !i.quitada).length;

            [...lista].forEach((archivo) => {
                if (!FORMATOS.includes(archivo.type)) return problemas.push(`${archivo.name}: formato no permitido.`);
                if (archivo.size > MAX_BYTES) return problemas.push(`${archivo.name}: supera los 2 MB.`);
                if (conservadas + estado.nuevas.length >= estado.maximo) return problemas.push(`Máximo ${estado.maximo} imágenes por vehículo.`);

                estado.nuevas.push({ archivo, url: URL.createObjectURL(archivo) });
            });

            mostrarAlerta(problemas);
            asegurarPerfil();
            dibujar();
        }

        const tablaEl = contenedor.querySelector('#tabla') || document.getElementById('tabla');
        if (tablaEl) {
            tablaEl.addEventListener('click', async (evento) => {
                const boton = evento.target.closest('[data-imagenes]');
                if (!boton) return;

                const urlPeticion = `${urlImagenesBase}/${boton.dataset.imagenes}/imagenes`;
                const { ok, estado: codigo, json } = await Tads.peticion(urlPeticion);
                if (!ok) return Tads.alertaDeError(codigo, json, 'No se pudieron cargar las imágenes');

                estado.vehiculoId = boton.dataset.imagenes;
                estado.maximo = json.maximo;
                estado.version = json.version;
                estado.guardadas = (json.imagenes || []).map((i) => ({ id: i.id, url: i.url, perfil: i.perfil, quitada: false }));
                estado.nuevas = [];
                const actual = estado.guardadas.find((i) => i.perfil);
                estado.perfil = actual ? { tipo: 'existing', id: actual.id } : null;

                if (nodo('imgNombre')) nodo('imgNombre').textContent = json.vehiculo.nombre;
                if (nodo('imgCodigo')) nodo('imgCodigo').innerHTML = '<i class="bi bi-upc-scan me-1"></i>' + Tads.escapar(json.vehiculo.codigo);
                if (nodo('imgPlaca')) nodo('imgPlaca').innerHTML = '<i class="bi bi-tag me-1"></i>' + Tads.escapar(json.vehiculo.placa);
                if (nodo('imgAnio')) nodo('imgAnio').innerHTML = '<i class="bi bi-calendar3 me-1"></i>' + Tads.escapar(json.vehiculo.anio);
                mostrarAlerta([]);
                dibujar();
                Tads.ventana('#ventanaImagenes').show();
            });
        }

        if (zona && entrada) {
            zona.addEventListener('click', () => entrada.click());
            zona.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); entrada.click(); } });
            entrada.addEventListener('click', (e) => e.stopPropagation());
            entrada.addEventListener('change', () => { agregarArchivos(entrada.files); entrada.value = ''; });
            ['dragenter', 'dragover'].forEach((n) => zona.addEventListener(n, (e) => { e.preventDefault(); zona.classList.add('encima'); }));
            ['dragleave', 'dragend', 'drop'].forEach((n) => zona.addEventListener(n, (e) => { e.preventDefault(); zona.classList.remove('encima'); }));
            zona.addEventListener('drop', (e) => agregarArchivos(e.dataTransfer.files));
        }

        ventanaEl.addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-orden]');
            if (!boton) return;

            const caja = boton.closest('.t-imagen');
            const imagen = caja?._imagen;
            if (!imagen) return;

            if (boton.dataset.orden === 'perfil') {
                if (imagen.quitada) return;
                estado.perfil = caja._esNueva ? { tipo: 'new', ref: imagen } : { tipo: 'existing', id: imagen.id };
            } else if (caja._esNueva) {
                estado.nuevas.splice(estado.nuevas.indexOf(imagen), 1);
                URL.revokeObjectURL(imagen.url);
                if (estado.perfil?.ref === imagen) estado.perfil = null;
            } else {
                imagen.quitada = !imagen.quitada;
                if (imagen.quitada && estado.perfil?.id === imagen.id) estado.perfil = null;
            }

            asegurarPerfil();
            dibujar();
        });

        const botonGuardar = nodo('botonGuardarImagenes');
        if (botonGuardar) {
            botonGuardar.addEventListener('click', async (evento) => {
                const boton = evento.currentTarget;
                if (boton.disabled) return;
                const datos = new FormData();
                datos.append('version', estado.version);

                estado.nuevas.forEach((img) => datos.append('images[]', img.archivo));
                estado.guardadas.filter((i) => i.quitada).forEach((i) => datos.append('delete[]', i.id));
                if (estado.perfil) {
                    datos.append('profile', estado.perfil.tipo === 'new'
                        ? `new:${estado.nuevas.indexOf(estado.perfil.ref)}`
                        : `existing:${estado.perfil.id}`);
                }

                const textoOriginal = boton.innerHTML;
                boton.disabled = true;
                boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

                const urlGuardar = `${urlImagenesBase}/${estado.vehiculoId}/imagenes`;
                const { ok, estado: codigo, json } = await Tads.peticion(urlGuardar, { metodo: 'POST', cuerpo: datos });

                boton.disabled = false;
                boton.innerHTML = textoOriginal;

                if (ok) {
                    estado.nuevas.forEach((i) => URL.revokeObjectURL(i.url));
                    Tads.ventana('#ventanaImagenes').hide();
                    gestorVehiculos.recargar();
                    return Tads.aviso(json.mensaje);
                }

                if (codigo === 422) mostrarAlerta([Tads.textoDeError(codigo, json)]);
                else Tads.alertaDeError(codigo, json);
            });
        }
    }

    /* ---------- Inicializador del Módulo ---------- */
    Tads.Modulos.vehiculos = {
        init: function (contenedor) {
            const urlListado = contenedor.dataset.urlListado;
            const urlRecurso = contenedor.dataset.urlRecurso;
            const urlCrear = contenedor.dataset.urlCrear;

            const gestorVehiculos = new Tads.Gestor({
                entidad: 'vehículo',
                icono: 'bi-truck-front',
                urlListado: urlListado,
                urlRecurso: urlRecurso,
                urlCrear: urlCrear,
                orden: [[2, 'asc']],
                columnas: [
                    { data: 'imagen', orderable: false, searchable: false,
                        render: (url) => `<img class="t-miniatura" src="${Tads.escapar(url)}" alt="Vehículo">` },
                    { data: 'nombre', render: (v) => `<strong>${Tads.escapar(v)}</strong>` },
                    { data: 'codigo', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                    { data: 'placa', render: (v) => `<span class="t-codigo">${Tads.escapar(v)}</span>` },
                    { data: 'anio' },
                    { data: 'carga', render: (v) => Number(v).toLocaleString('es-PE', { maximumFractionDigits: 2 }) },
                    { data: 'marca' },
                    { data: 'modelo' },
                    { data: 'tipo', render: (v) => Tads.insignia(v, 'azul') },
                    { data: 'color', render: (nombre, tipo, fila) =>
                        `<span class="t-muestra-color" style="background:${Tads.escapar(fila.color_codigo)}" title="${Tads.escapar(nombre)}"></span>` },
                    { data: 'activo', render: (v) => v ? Tads.insignia('Activo', 'verde') : Tads.insignia('Inactivo', 'rojo') },
                    { data: null, orderable: false, searchable: false, className: 'text-center',
                        render: (fila) => Tads.acciones(
                            Tads.botonEditar(fila.id),
                            `<button type="button" class="btn t-boton-icono t-info" data-imagenes="${fila.id}" data-bs-toggle="tooltip" title="Imágenes (${fila.total_imagenes})"><i class="bi bi-images"></i></button>`,
                            Tads.botonEliminar(fila.id, `${fila.nombre} (${fila.placa})`),
                        ) },
                ],
            });

            contenedor._gestor = gestorVehiculos;
            iniciarGestorImagenes(contenedor, gestorVehiculos);
        },
    };

    if (typeof Tads.iniciarModulos === 'function') {
        Tads.iniciarModulos();
    }
})(window);
