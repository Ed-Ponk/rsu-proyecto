@php($editando = isset($usuario))

<div class="t-alerta-form"></div>

<div class="mb-3">
    <label for="usuarioNombre" class="form-label t-obligatorio">Nombre</label>
    <input type="text" name="name" id="usuarioNombre" class="form-control" maxlength="100" required
        placeholder="Nombre completo" value="{{ old('name', $usuario->name ?? '') }}">
</div>

<div class="mb-3">
    <label for="usuarioCorreo" class="form-label t-obligatorio">Correo electrónico</label>
    <input type="email" name="email" id="usuarioCorreo" class="form-control" maxlength="150" required
        placeholder="correo@municipalidad.gob.pe" value="{{ old('email', $usuario->email ?? '') }}">
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label for="usuarioClave" class="form-label {{ $editando ? '' : 't-obligatorio' }}">{{ $editando ? 'Nueva contraseña' : 'Contraseña' }}</label>
        <input type="password" name="password" id="usuarioClave" class="form-control" maxlength="72" autocomplete="new-password"
            @required(! $editando) placeholder="{{ $editando ? 'Déjela vacía para conservar la actual' : 'Mínimo 8 caracteres' }}">
    </div>
    <div class="col-md-6">
        <label for="usuarioClaveConfirmacion" class="form-label {{ $editando ? '' : 't-obligatorio' }}">Confirmar contraseña</label>
        <input type="password" name="password_confirmation" id="usuarioClaveConfirmacion" class="form-control" maxlength="72"
            autocomplete="new-password" @required(! $editando) placeholder="Repita la contraseña">
    </div>
    <div class="col-12 form-text mt-0">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</div>
</div>

<div class="form-check form-switch mb-3">
    <input type="hidden" name="status" value="0">
    <input class="form-check-input" type="checkbox" role="switch" name="status" id="usuarioActivo" value="1"
        @checked(old('status', $usuario->status ?? true))>
    <label class="form-check-label fw-semibold" for="usuarioActivo">Cuenta activa (puede iniciar sesión)</label>
</div>

@include('admin.partials.pie-formulario')
