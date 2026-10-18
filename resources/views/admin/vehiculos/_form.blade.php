<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-md-6">
        <label for="vehiculoCodigo" class="form-label t-obligatorio">Código</label>
        <input type="text" name="code" id="vehiculoCodigo" class="form-control text-uppercase" maxlength="50" required
            placeholder="Ej. VEH-001" value="{{ old('code', $vehiculo->code ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="vehiculoTipo" class="form-label t-obligatorio">Tipo de vehículo</label>
        @include('admin.partials.buscador', [
            'id' => 'vehiculoTipo', 'nombre' => 'type_id', 'url' => route('admin.tiposvehiculo.buscar'),
            'placeholder' => 'Escriba el tipo de vehículo...', 'elegido' => $elegidos['tipo'],
            'mensaje' => 'Seleccione un tipo de vehículo de la lista.', 'cambiar' => 'Cambiar tipo',
            'vacio' => 'No hay tipos de vehículo registrados.', 'sinCoincidencias' => 'No hay tipos que coincidan con «:texto».',
        ])
    </div>

    <div class="col-md-6">
        <label for="vehiculoNombre" class="form-label t-obligatorio">Nombre del vehículo</label>
        <input type="text" name="name" id="vehiculoNombre" class="form-control" maxlength="100" required
            placeholder="Ej. Vehículo 01" value="{{ old('name', $vehiculo->name ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="vehiculoPlaca" class="form-label t-obligatorio">Placa</label>
        <input type="text" name="plate" id="vehiculoPlaca" class="form-control text-uppercase" maxlength="10" required
            placeholder="ABC-123" value="{{ old('plate', $vehiculo->plate ?? '') }}"
            pattern="^([A-Za-z0-9]{6}|[A-Za-z0-9]{2}-[A-Za-z0-9]{4}|[A-Za-z0-9]{3}-[A-Za-z0-9]{3})$"
            title="Formatos válidos: XXXXXX, XX-XXXX o XXX-XXX">
        <div class="form-text">Formatos: XXXXXX, XX-XXXX o XXX-XXX. No puede repetirse.</div>
    </div>

    <div class="col-md-6">
        <label for="vehiculoAnio" class="form-label t-obligatorio">Año</label>
        <input type="number" name="year" id="vehiculoAnio" class="form-control" required
            min="{{ config('proyecto.vehiculos.anio_minimo') }}" max="{{ now()->year + 1 }}"
            placeholder="{{ now()->year }}" value="{{ old('year', $vehiculo->year ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="vehiculoColor" class="form-label t-obligatorio">Color</label>
        @include('admin.partials.buscador', [
            'id' => 'vehiculoColor', 'nombre' => 'color_id', 'url' => route('admin.colores.buscar'),
            'placeholder' => 'Escriba el nombre del color...', 'elegido' => $elegidos['color'],
            'mensaje' => 'Seleccione un color de la lista.', 'cambiar' => 'Cambiar color',
            'vacio' => 'No hay colores registrados.', 'sinCoincidencias' => 'No hay colores que coincidan con «:texto».',
        ])
    </div>

    <div class="col-md-6">
        <label for="vehiculoMarca" class="form-label t-obligatorio">Marca</label>
        @include('admin.partials.buscador', [
            'id' => 'vehiculoMarca', 'nombre' => 'brand_id', 'url' => route('admin.marcas.buscar'), 'forma' => 'logo',
            'placeholder' => 'Escriba el nombre de la marca...', 'elegido' => $elegidos['marca'],
            'mensaje' => 'Seleccione una marca de la lista.', 'cambiar' => 'Cambiar marca',
            'vacio' => 'No hay marcas registradas.', 'sinCoincidencias' => 'No hay marcas que coincidan con «:texto».',
        ])
    </div>
    <div class="col-md-6">
        <label for="vehiculoModelo" class="form-label t-obligatorio">Modelo</label>
        @include('admin.partials.buscador', [
            'id' => 'vehiculoModelo', 'nombre' => 'model_id', 'url' => route('admin.modelos.buscar'),
            'placeholder' => 'Escriba el nombre del modelo...', 'elegido' => $elegidos['modelo'],
            'mensaje' => 'Seleccione un modelo de la lista.', 'cambiar' => 'Cambiar modelo',
            'vacio' => 'Esta marca no tiene modelos registrados.', 'sinCoincidencias' => 'No hay modelos que coincidan con «:texto».',
            'depende' => '#vehiculoMarcaId', 'parametro' => 'marca', 'sinDependencia' => 'Primero elija la marca...',
        ])
        <div class="form-text">Solo se buscan los modelos de la marca elegida.</div>
    </div>

    <div class="col-md-6">
        <label for="vehiculoCarga" class="form-label t-obligatorio">Capacidad de carga (Tn)</label>
        <input type="number" step="0.01" min="0.01" name="load_capacity" id="vehiculoCarga" class="form-control" required
            placeholder="Ej. 9.5" value="{{ old('load_capacity', $vehiculo->load_capacity ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="vehiculoCombustible" class="form-label t-obligatorio">Capacidad de combustible (L)</label>
        <input type="number" step="0.01" min="0.01" name="fuel_capacity" id="vehiculoCombustible" class="form-control" required
            placeholder="Ej. 200" value="{{ old('fuel_capacity', $vehiculo->fuel_capacity ?? '') }}">
    </div>

    <div class="col-md-6">
        <label for="vehiculoCompactacion" class="form-label t-obligatorio">Capacidad de compactación (Tn)</label>
        <input type="number" step="0.01" min="0" name="compact_capacity" id="vehiculoCompactacion" class="form-control" required
            placeholder="Ej. 8 (0 si no compacta)" value="{{ old('compact_capacity', $vehiculo->compact_capacity ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="vehiculoPersonas" class="form-label t-obligatorio">Capacidad de personas</label>
        <input type="number" min="1" max="10" name="occupant_capacity" id="vehiculoPersonas" class="form-control" required
            placeholder="Ej. 3" value="{{ old('occupant_capacity', $vehiculo->occupant_capacity ?? '') }}">
        <div class="form-text">Define cuántas personas se podrán asignar al vehículo en la programación.</div>
    </div>

    <div class="col-12">
        <label for="vehiculoDescripcion" class="form-label">Descripción</label>
        <textarea name="description" id="vehiculoDescripcion" class="form-control" rows="2" maxlength="500"
            placeholder="Describa el vehículo">{{ old('description', $vehiculo->description ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="status" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="status" id="vehiculoActivo" value="1"
                @checked(old('status', $vehiculo->status ?? true))>
            <label class="form-check-label fw-semibold" for="vehiculoActivo">Vehículo activo (disponible para programar)</label>
        </div>
        <div class="form-text">Las imágenes se gestionan con el botón <i class="bi bi-images"></i> de la lista.</div>
    </div>
</div>

@include('admin.partials.pie-formulario')
