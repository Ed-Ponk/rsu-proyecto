@extends('layouts.admin')

@section('title', 'Zonas')

@section('plugins.Leaflet', true)

@section('content')
    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-geo-alt-fill"></i></span>
                Lista de zonas
            </h1>
            <p class="t-subtitulo">Zonas de recolección. Cada una se define por el perímetro que se traza en el mapa.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn t-boton t-boton-secundario" id="botonVerMapa"><i class="bi bi-map-fill me-1"></i> Ver mapa de zonas</button>
            <button type="button" class="btn t-boton" id="botonNuevo"><i class="bi bi-plus-lg me-1"></i> Nueva zona</button>
        </div>
    </div>

    <div class="t-tarjeta"
         id="moduloZonas"
         data-tads-modulo="zonas"
         data-url-listado="{{ route('admin.zonas.datos') }}"
         data-url-recurso="{{ url('admin/zonas') }}"
         data-url-crear="{{ route('admin.zonas.create') }}"
         data-url-mapa="{{ route('admin.zonas.mapa') }}"
         data-ubigeo='@json($ubigeo['departamentos'])'
         data-mapa-inicial='@json($ubigeo['mapa'])'>
        <div class="t-tarjeta-cuerpo">
            <div class="table-responsive">
                <table class="table t-tabla" id="tabla">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Distrito</th>
                            <th>Provincia</th>
                            <th>Departamento</th>
                            <th>Descripción</th>
                            <th>Coordenadas</th>
                            <th>Estado</th>
                            <th>Fecha creación</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.partials.ventana-formulario', ['tamano' => 'modal-xl'])

    {{-- Mapa de una zona: datos, perímetro y tabla de coordenadas --}}
    <div class="modal fade t-modal" id="ventanaZona" tabindex="-1" aria-labelledby="tituloZona" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloZona"><i class="bi bi-map-fill me-2"></i>Mapa de la zona</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="t-zona-ficha">
                                <span class="t-zona-ficha-icono"><i class="bi bi-geo-alt-fill"></i></span>
                                <div>
                                    <strong id="zonaVerNombre">—</strong>
                                    <small id="zonaVerDistrito">—</small>
                                </div>
                            </div>
                            <div class="row g-2 my-1">
                                <div class="col-6"><div class="t-dato t-dato-azul"><small>Puntos</small><strong id="zonaVerPuntos">0</strong></div></div>
                                <div class="col-6"><div class="t-dato t-dato-verde"><small>Residuos</small><strong id="zonaVerResiduos">N/A</strong></div></div>
                                <div class="col-6"><div class="t-dato t-dato-ambar"><small>Departamento</small><strong id="zonaVerDepartamento">—</strong></div></div>
                                <div class="col-6"><div class="t-dato t-dato-celeste"><small>Área</small><strong id="zonaVerArea">—</strong></div></div>
                            </div>
                            <h6 class="t-seccion-titulo mt-3"><i class="bi bi-card-text"></i> Descripción de la zona</h6>
                            <p id="zonaVerDescripcion" class="small mb-3">—</p>
                            <h6 class="t-seccion-titulo"><i class="bi bi-pin-map-fill"></i> Coordenadas del polígono</h6>
                            <div class="t-coord-tabla">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>#</th><th>Latitud</th><th>Longitud</th></tr></thead>
                                    <tbody id="zonaVerCoordenadas"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <h6 class="t-seccion-titulo"><i class="bi bi-map"></i> Visualización en mapa</h6>
                            <div id="zonaVerMapa" class="t-mapa t-mapa-grande"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Explorador de todas las zonas --}}
    <div class="modal fade t-modal" id="ventanaExplorador" tabindex="-1" aria-labelledby="tituloExplorador" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloExplorador"><i class="bi bi-map-fill me-2"></i>Explorador de zonas geográficas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-lg-3">
                            <h6 class="t-seccion-titulo"><i class="bi bi-funnel-fill"></i> Filtros de búsqueda</h6>
                            <label class="form-label small fw-semibold" for="exploradorDepartamento">Departamento</label>
                            <select id="exploradorDepartamento" class="form-select form-select-sm mb-2"></select>
                            <label class="form-label small fw-semibold" for="exploradorProvincia">Provincia</label>
                            <select id="exploradorProvincia" class="form-select form-select-sm mb-2"></select>
                            <label class="form-label small fw-semibold" for="exploradorDistrito">Distrito</label>
                            <select id="exploradorDistrito" class="form-select form-select-sm mb-3"></select>

                            <h6 class="t-seccion-titulo"><i class="bi bi-bar-chart-fill"></i> Estadísticas</h6>
                            <div class="d-flex flex-column gap-2 mb-3">
                                <div class="t-dato t-dato-celeste"><small>Zonas encontradas</small><strong id="exploradorZonas">0</strong></div>
                                <div class="t-dato t-dato-verde"><small>Activas</small><strong id="exploradorActivas">0</strong></div>
                                <div class="t-dato t-dato-azul"><small>Total de puntos</small><strong id="exploradorPuntos">0</strong></div>
                            </div>

                            <h6 class="t-seccion-titulo"><i class="bi bi-palette-fill"></i> Leyenda del mapa</h6>
                            <div class="small"><span class="t-leyenda" style="background:#16a34a"></span> Zonas activas</div>
                            <div class="small"><span class="t-leyenda" style="background:#6b7280"></span> Zonas inactivas</div>
                        </div>
                        <div class="col-lg-9">
                            <div class="small text-muted mb-2" id="exploradorTexto"><i class="bi bi-info-circle-fill me-1"></i> Cargando zonas...</div>
                            <div id="exploradorMapa" class="t-mapa t-mapa-grande"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn t-boton-cancelar" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i> Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@stop
