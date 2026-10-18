<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-md-6">
        <label for="vehiculoCodigo" class="form-label t-obligatorio">Código</label>
        <input type="text" name="code" id="vehiculoCodigo" class="form-control text-uppercase" maxlength="50" required
            placeholder="Ej. VEH-001" value="{{ old('code', $vehiculo->code ?? '') }}">
    </div>
    <div class="col-md-6">
        <label for="vehiculoTipo" class="form-label t-obligatorio">Tipo de vehículo</label>
        <select name="type_id" id="vehiculoTipo" class="form-select" required>
            <option value="">Seleccione un tipo</option>
            @foreach ($tipos as $tipo)
                <option value="{{ $tipo->id }}" @selected((int) old('type_id', $vehiculo->type_id ?? 0) === $tipo->id)>{{ $tipo->name }}</option>
            @endforeach
        </select>
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
        <select name="color_id" id="vehiculoColor" class="form-select" required>
            <option value="">Seleccione un color</option>
            @foreach ($colores as $color)
                <option value="{{ $color->id }}" @selected((int) old('color_id', $vehiculo->color_id ?? 0) === $color->id)>{{ $color->name }} ({{ $color->code }})</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label for="vehiculoMarca" class="form-label t-obligatorio">Marca</label>
        <select name="brand_id" id="vehiculoMarca" class="form-select" required>
            <option value="">Seleccione una marca</option>
            @foreach ($marcas as $marca)
                <option value="{{ $marca->id }}" @selected((int) old('brand_id', $vehiculo->brand_id ?? 0) === $marca->id)>{{ $marca->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label for="vehiculoModelo" class="form-label t-obligatorio">Modelo</label>
        <select name="model_id" id="vehiculoModelo" class="form-select" required data-elegido="{{ old('model_id', $vehiculo->model_id ?? '') }}">
            <option value="">Seleccione un modelo</option>
            @foreach ($modelos as $modelo)
                <option value="{{ $modelo->id }}" data-marca="{{ $modelo->brand_id }}">{{ $modelo->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Solo se muestran los modelos de la marca elegida.</div>
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
