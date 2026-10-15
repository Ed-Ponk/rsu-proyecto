@extends('adminlte::page')

@section('title', 'Vehículos')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-truck-front',
        'titulo' => 'Lista de vehículos',
        'subtitulo' => 'Flota que se usa en la recolección de residuos sólidos.',
        'boton' => 'Nuevo vehículo',
    ])

    <div class="t-tarjeta">
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Placa</th>
                            <th>Año</th>
                            <th>Carga (Tn)</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Tipo</th>
                            <th>Color</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-xl'])

    {{-- Ventana "Gestión de imágenes": los cambios se aplican todos juntos al presionar Guardar. --}}
    <div class="modal fade t-modal" id="ventanaImagenes" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-images me-2"></i>Gestión de imágenes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="t-banner-vehiculo">
                        <div class="t-banner-icono"><i class="bi bi-truck-front"></i></div>
                        <div>
                            <h5 id="imgNombre">Vehículo</h5>
                            <span class="t-chip" id="imgCodigo"></span>
                            <span class="t-chip" id="imgPlaca"></span>
                            <span class="t-chip" id="imgAnio"></span>
                        </div>
                    </div>

                    <div class="t-alerta-form" id="imgAlerta"></div>

                    <div class="t-seccion"><i class="bi bi-cloud-arrow-up"></i> Agregar nuevas imágenes</div>
                    <div class="t-zona-arrastre" id="zonaArrastre" tabindex="0" role="button" aria-label="Elegir imágenes">
                        <i class="bi bi-images"></i>
                        <h6>Arrastre y suelte las imágenes aquí</h6>
                        <p>o haga clic para elegir archivos</p>
                        <span class="t-insignia t-azul">Formatos: JPEG, PNG, JPG, GIF</span>
                        <span class="t-insignia t-ambar">Máx. 2 MB por imagen</span>
                        <input type="file" id="archivosImagen" class="d-none" accept="image/jpeg,image/png,image/gif" multiple>
                    </div>

                    <div id="bloqueNuevas" class="d-none">
                        <div class="t-seccion"><i class="bi bi-hourglass-split"></i> Por guardar <span class="t-insignia t-ambar" id="contadorNuevas">0</span></div>
                        <div class="t-imagenes" id="listaNuevas"></div>
                    </div>

                    <div class="t-seccion">
                        <i class="bi bi-collection"></i> Imágenes guardadas <span class="t-insignia t-azul" id="contadorGuardadas">0</span>
                        <small class="ms-auto text-muted text-capitalize fw-normal">
                            <i class="bi bi-star-fill text-warning"></i> Perfil &nbsp; <i class="bi bi-x-circle-fill text-danger"></i> Quitar
                        </small>
                    </div>
                    <div class="t-imagenes" id="listaGuardadas"></div>
                    <p class="text-muted text-center my-3 d-none" id="sinImagenes">Este vehículo todavía no tiene imágenes.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <small class="text-muted me-auto">Los cambios se aplican al presionar «Guardar».</small>
                    <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
                    <button type="button" class="btn t-boton" id="botonGuardarImagenes"><i class="bi bi-floppy-fill me-1"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        /* ---------- Formulario: los modelos que se ofrecen dependen de la marca elegida ---------- */
        function activarModelosPorMarca(cuerpo) {
            const marca = cuerpo.querySelector('#vehiculoMarca');
            const modelo = cuerpo.querySelector('#vehiculoModelo');
            const todos = [...modelo.querySelectorAll('option[data-marca]')].map((op) => op.cloneNode(true));
            const elegido = String(modelo.dataset.elegido || '');

            function ofrecerModelos(seleccionar) {
                modelo.querySelectorAll('option[data-marca]').forEach((op) => op.remove());

                if (!marca.value) {
                    modelo.disabled = true;
                    modelo.value = '';
                    return;
                }

                todos.filter((op) => op.dataset.marca === marca.value).forEach((op) => modelo.appendChild(op.cloneNode(true)));
                modelo.disabled = false;
                modelo.value = seleccionar || '';
            }

            marca.addEventListener('change', () => ofrecerModelos(''));
            ofrecerModelos(elegido);
        }

        const gestorVehiculos = new Tads.Gestor({
            entidad: 'vehículo',
            icono: 'bi-truck-front',
            urlListado: "{{ route('admin.vehiculos.index') }}",
            urlRecurso: "{{ url('admin/vehiculos') }}",
            urlCrear: "{{ route('admin.vehiculos.create') }}",
            orden: [[2, 'asc']],
            alCargarFormulario: activarModelosPorMarca,
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

        /* ---------- Gestión de imágenes ---------- */
        (function () {
            const MAX_BYTES = 2 * 1024 * 1024;
            const FORMATOS = ['image/jpeg', 'image/png', 'image/gif'];

            // Todo lo que el usuario prepara en pantalla vive aquí hasta que presiona Guardar.
            const estado = { vehiculoId: null, maximo: 12, guardadas: [], nuevas: [], perfil: null };

            const ventanaEl = document.getElementById('ventanaImagenes');
            const alerta = document.getElementById('imgAlerta');
            const zona = document.getElementById('zonaArrastre');
            const entrada = document.getElementById('archivosImagen');
            const nodo = (id) => document.getElementById(id);

            const mostrarAlerta = (lineas) => {
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

                nodo('contadorGuardadas').textContent = estado.guardadas.length;
                nodo('contadorNuevas').textContent = estado.nuevas.length;
                nodo('bloqueNuevas').classList.toggle('d-none', estado.nuevas.length === 0);
                nodo('sinImagenes').classList.toggle('d-none', estado.guardadas.length > 0);
            }

            // Si no queda ninguna imagen de perfil válida, se elige la primera disponible.
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

            // Abrir la ventana desde el botón azul de cada fila
            document.getElementById('tabla').addEventListener('click', async (evento) => {
                const boton = evento.target.closest('[data-imagenes]');
                if (!boton) return;

                const { ok, estado: codigo, json } = await Tads.peticion(`{{ url('admin/vehiculos') }}/${boton.dataset.imagenes}/imagenes`);
                if (!ok) return Tads.alertaDeError(codigo, json, 'No se pudieron cargar las imágenes');

                estado.vehiculoId = boton.dataset.imagenes;
                estado.maximo = json.maximo;
                estado.guardadas = json.imagenes.map((i) => ({ id: i.id, url: i.url, perfil: i.perfil, quitada: false }));
                estado.nuevas = [];
                const actual = estado.guardadas.find((i) => i.perfil);
                estado.perfil = actual ? { tipo: 'existing', id: actual.id } : null;

                nodo('imgNombre').textContent = json.vehiculo.nombre;
                nodo('imgCodigo').innerHTML = '<i class="bi bi-upc-scan me-1"></i>' + Tads.escapar(json.vehiculo.codigo);
                nodo('imgPlaca').innerHTML = '<i class="bi bi-tag me-1"></i>' + Tads.escapar(json.vehiculo.placa);
                nodo('imgAnio').innerHTML = '<i class="bi bi-calendar3 me-1"></i>' + Tads.escapar(json.vehiculo.anio);
                mostrarAlerta([]);
                dibujar();
                Tads.ventana('#ventanaImagenes').show();
            });

            // Zona de arrastre
            zona.addEventListener('click', () => entrada.click());
            zona.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); entrada.click(); } });
            entrada.addEventListener('click', (e) => e.stopPropagation());
            entrada.addEventListener('change', () => { agregarArchivos(entrada.files); entrada.value = ''; });
            ['dragenter', 'dragover'].forEach((n) => zona.addEventListener(n, (e) => { e.preventDefault(); zona.classList.add('encima'); }));
            ['dragleave', 'dragend', 'drop'].forEach((n) => zona.addEventListener(n, (e) => { e.preventDefault(); zona.classList.remove('encima'); }));
            zona.addEventListener('drop', (e) => agregarArchivos(e.dataTransfer.files));

            // Estrella (perfil) y X (quitar) de cada tarjeta
            ventanaEl.addEventListener('click', (evento) => {
                const boton = evento.target.closest('[data-orden]');
                if (!boton) return;

                const caja = boton.closest('.t-imagen');
                const imagen = caja._imagen;

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

            // Guardar todo en una sola petición
            nodo('botonGuardarImagenes').addEventListener('click', async (evento) => {
                const boton = evento.currentTarget;
                const datos = new FormData();

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

                const { ok, estado: codigo, json } = await Tads.peticion(`{{ url('admin/vehiculos') }}/${estado.vehiculoId}/imagenes`, { metodo: 'POST', cuerpo: datos });

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
        })();
    </script>
@stop
