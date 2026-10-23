@extends('adminlte::page')

@section('title', 'Vehículos')

@section('content')
    @include('admin.partials.cabecera', [
        'icono' => 'bi-truck-front',
        'titulo' => 'Lista de vehículos',
        'subtitulo' => 'Flota que se usa en la recolección de residuos sólidos.',
        'boton' => 'Nuevo vehículo',
    ])

    <div class="t-tarjeta"
         id="moduloVehiculos"
         data-tads-modulo="vehiculos"
         data-url-listado="{{ route('admin.vehiculos.datos') }}"
         data-url-recurso="{{ url('admin/vehiculos') }}"
         data-url-crear="{{ route('admin.vehiculos.create') }}"
         data-url-imagenes="{{ url('admin/vehiculos') }}">
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
