@php
    $logoActual = isset($marca) && filled($marca->logo)
        ? (preg_match('#^(https?://|/)#', $marca->logo) ? $marca->logo : asset($marca->logo))
        : null;
@endphp

<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-md-7">
        <div class="mb-3">
            <label for="marcaNombre" class="form-label t-obligatorio">Nombre</label>
            <input type="text" name="name" id="marcaNombre" class="form-control" maxlength="100" required
                placeholder="Nombre de la marca" value="{{ old('name', $marca->name ?? '') }}">
        </div>
        <div class="mb-3">
            <label for="marcaDescripcion" class="form-label">Descripción</label>
            <textarea name="description" id="marcaDescripcion" class="form-control" rows="4" maxlength="500"
                placeholder="Describa la marca">{{ old('description', $marca->description ?? '') }}</textarea>
        </div>
    </div>

    <div class="col-md-5">
        <label class="form-label">Logo <span class="text-muted fw-normal">(opcional)</span></label>
        <div class="t-caja-foto" id="cajaLogo">
            @if ($logoActual)
                <div><img src="{{ $logoActual }}" alt="Logo actual"><small>Logo actual · clic para cambiarlo</small></div>
            @else
                <div><i class="bi bi-image"></i><small>Haga clic para elegir una imagen</small></div>
            @endif
        </div>
        <input type="file" name="logo" id="archivoLogo" class="d-none" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
        <div class="form-text">JPG, PNG, WEBP, GIF o SVG. Máximo 2 MB.</div>
    </div>
</div>

@include('admin.partials.pie-formulario')
