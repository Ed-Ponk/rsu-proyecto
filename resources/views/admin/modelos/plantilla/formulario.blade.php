<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="modeloNombre" class="form-label t-obligatorio">Nombre del modelo</label>
    <input type="text" name="name" id="modeloNombre" class="form-control" maxlength="100" required
        placeholder="Ej. Atego, Dyna, FM" value="{{ old('name', $modelo->name ?? '') }}">
</div>

<div class="mb-3">
    <label for="modeloCodigo" class="form-label t-obligatorio">Código del modelo</label>
    <input type="text" name="code" id="modeloCodigo" class="form-control text-uppercase" maxlength="30" required
        placeholder="Ej. ATEGO-MB" value="{{ old('code', $modelo->code ?? '') }}">
    <div class="form-text">Código único para identificar el modelo.</div>
</div>

<div class="mb-3">
    <label for="modeloMarca" class="form-label t-obligatorio">Marca</label>
    <select name="brand_id" id="modeloMarca" class="form-select" required>
        <option value="">Seleccione una marca</option>
        @foreach ($marcas as $marca)
            <option value="{{ $marca->id }}" @selected((int) old('brand_id', $modelo->brand_id ?? 0) === $marca->id)>{{ $marca->name }}</option>
        @endforeach
    </select>
</div>

<div class="mb-1">
    <label for="modeloDescripcion" class="form-label">Descripción</label>
    <textarea name="description" id="modeloDescripcion" class="form-control" rows="3" maxlength="500"
        placeholder="Describa el modelo">{{ old('description', $modelo->description ?? '') }}</textarea>
</div>

@include('admin.partials.pie-formulario')
