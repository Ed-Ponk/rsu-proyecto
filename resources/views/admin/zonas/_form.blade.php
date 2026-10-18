@php
    $editando = isset($zona);
    $estadoActual = (string) old('status', $editando ? (int) $zona->status : 1);
    $porDefecto = $ubigeo['por_defecto'];
@endphp

<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-12">
        <label for="zonaNombre" class="form-label t-obligatorio">Nombre de la zona</label>
        <input type="text" name="name" id="zonaNombre" class="form-control" maxlength="100" required
            placeholder="Nombre de la zona" value="{{ old('name', $zona->name ?? '') }}">
    </div>

    <div class="col-12">
        <div class="row g-3" id="zonaUbicacion"
             data-ubigeo='@json($ubigeo['departamentos'])'
             data-actual='@json(['department' => $zona->department ?? $porDefecto['department'], 'province' => $zona->province ?? $porDefecto['province'], 'district' => $zona->district ?? $porDefecto['district']])'>
            <div class="col-md-4">
                <label for="zonaDepartamento" class="form-label t-obligatorio">Departamento</label>
                <select name="department" id="zonaDepartamento" class="form-select" required></select>
            </div>
            <div class="col-md-4">
                <label for="zonaProvincia" class="form-label t-obligatorio">Provincia</label>
                <select name="province" id="zonaProvincia" class="form-select" required></select>
            </div>
            <div class="col-md-4">
                <label for="zonaDistrito" class="form-label t-obligatorio">Distrito</label>
                <select name="district" id="zonaDistrito" class="form-select" required></select>
            </div>
        </div>
    </div>

    <div class="col-12">
        <label for="zonaDescripcion" class="form-label">Descripción</label>
        <textarea name="description" id="zonaDescripcion" class="form-control" rows="2" maxlength="500"
            placeholder="Agregue una descripción de la zona">{{ old('description', $zona->description ?? '') }}</textarea>
    </div>

    <div class="col-md-6">
        <label for="zonaResiduos" class="form-label">Residuos promedio (kg)</label>
        <input type="number" name="average_waste" id="zonaResiduos" class="form-control" min="0" step="0.01" placeholder="Ej: 150.50"
            value="{{ old('average_waste', isset($zona) && $zona->average_waste !== null ? number_format((float) $zona->average_waste, 2, '.', '') : '') }}">
        <div class="form-text">Cantidad promedio de residuos en kilogramos por día.</div>
    </div>
    <div class="col-md-6">
        <label for="zonaEstado" class="form-label">Estado</label>
        <select name="status" id="zonaEstado" class="form-select">
            <option value="1" @selected($estadoActual === '1')>Activo</option>
            <option value="0" @selected($estadoActual === '0')>Inactivo</option>
        </select>
    </div>

    <div class="col-12">
        <h6 class="t-seccion-titulo"><i class="bi bi-geo-alt-fill"></i> Coordenadas del perímetro <span class="text-danger">*</span></h6>

        <div class="t-zona-editor"
             id="zonaEditor"
             data-vertices='@json($editando ? $zona->vertices() : [])'
             data-zona-id="{{ $zona->id ?? '' }}"
             data-url-verificar="{{ route('admin.zonas.verificar') }}"
             data-url-buscar-direccion="{{ route('admin.zonas.direccion') }}"
             data-mapa-inicial='@json($ubigeo['mapa'])'>
            <label for="zonaDireccion" class="form-label fw-semibold small">Buscar dirección dentro del distrito</label>
            <div class="t-buscador-direccion mb-2">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="zonaDireccion" class="form-control" autocomplete="off"
                        placeholder="Escriba una dirección o institución (ej: Av. Los Próceres, Plaza de Armas)">
                </div>
                <ul class="t-direcciones" id="zonaDirecciones" hidden></ul>
            </div>

            <div id="zonaFilas" class="d-flex flex-column gap-2 mb-2"></div>

            <div class="d-flex flex-wrap gap-2 mb-1">
                <button type="button" class="btn t-boton btn-sm" id="zonaAgregar"><i class="bi bi-plus-lg me-1"></i> Agregar coordenada</button>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" id="zonaLimpiar"><i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar mapa y coordenadas</button>
            </div>
            <div class="form-text mb-3"><span id="zonaConteo">0 coordenadas.</span> Mínimo 3 coordenadas para definir un perímetro.</div>

            <label class="form-label fw-semibold small">Mapa interactivo de la zona</label>

            <div class="t-mapa-barra" id="zonaBarra">
                <div class="t-mapa-grupo" data-grupo="reposo">
                    <button type="button" class="btn t-boton btn-sm" data-accion="dibujar"><i class="bi bi-pentagon-fill me-1"></i> Dibujar perímetro</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-accion="editar"><i class="bi bi-pencil-square me-1"></i> Editar puntos</button>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-3" data-accion="borrar"><i class="bi bi-trash3 me-1"></i> Borrar perímetro</button>
                </div>
                <div class="t-mapa-grupo" data-grupo="dibujando" hidden>
                    <button type="button" class="btn t-boton btn-sm" data-accion="terminar"><i class="bi bi-check-lg me-1"></i> Terminar</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-accion="deshacer"><i class="bi bi-arrow-counterclockwise me-1"></i> Deshacer último punto</button>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-3" data-accion="cancelarDibujo"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
                </div>
                <div class="t-mapa-grupo" data-grupo="editando" hidden>
                    <button type="button" class="btn t-boton btn-sm" data-accion="guardarEdicion"><i class="bi bi-check-lg me-1"></i> Guardar cambios</button>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-3" data-accion="cancelarEdicion"><i class="bi bi-x-lg me-1"></i> Cancelar</button>
                </div>
            </div>
            <div class="t-mapa-guia" id="zonaGuia"></div>

            <div id="zonaMapa" class="t-mapa"></div>
            <div id="zonaAviso" class="t-nota t-nota-roja mt-2" role="alert" hidden></div>
            <div class="form-text">Las demás zonas se muestran en rojo como referencia; el perímetro no puede invadirlas.</div>
        </div>
    </div>
</div>

@include('admin.partials.pie-formulario')
