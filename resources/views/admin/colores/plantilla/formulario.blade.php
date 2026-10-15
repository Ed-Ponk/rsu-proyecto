@php($codigo = old('code', $color->code ?? '#0B5CAD'))

<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="colorNombre" class="form-label t-obligatorio">Nombre del color</label>
    <input type="text" name="name" id="colorNombre" class="form-control" maxlength="100" required
        placeholder="Ej. Azul institucional" value="{{ old('name', $color->name ?? '') }}">
</div>

<div class="mb-3">
    <label for="colorCodigo" class="form-label t-obligatorio">Código del color (RGB)</label>
    <div class="input-group">
        <input type="text" name="code" id="colorCodigo" class="form-control text-uppercase" maxlength="7" required
            placeholder="#104CAD" pattern="^#[0-9A-Fa-f]{6}$" title="Use el formato #RRGGBB" value="{{ $codigo }}">
        <input type="color" id="selectorColor" class="form-control form-control-color t-selector-color" title="Elegir color" value="{{ $codigo }}">
    </div>
    <div class="form-text">Elija el color en la paleta o escriba el código hexadecimal (#RRGGBB).</div>
</div>

<div class="mb-3">
    <label for="colorDescripcion" class="form-label">Descripción</label>
    <textarea name="description" id="colorDescripcion" class="form-control" rows="2" maxlength="500"
        placeholder="Describa el color">{{ old('description', $color->description ?? '') }}</textarea>
</div>

<label class="form-label">Vista previa</label>
<div id="vistaColor" class="t-vista-color" style="background-color: {{ $codigo }}">{{ strtoupper($codigo) }}</div>

@include('admin.partials.pie-formulario')
