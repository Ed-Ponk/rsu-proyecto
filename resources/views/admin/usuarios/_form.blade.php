@php
    $editando = isset($usuario);
    $fotoActual = $editando && filled($usuario->profile_photo_path) ? $usuario->profile_photo_url : null;
    $tipoActual = (string) old('role', $usuario->role ?? '');
    $estadoActual = (string) old('status', ($usuario->status ?? true) ? '1' : '0');
    $esPropio = $editando && $usuario->is(auth()->user());
@endphp

<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="usuarioNombre" class="form-label t-obligatorio">Nombre</label>
                <input type="text" name="name" id="usuarioNombre" class="form-control" maxlength="100" required
                    placeholder="Ingrese los nombres" value="{{ old('name', $usuario->name ?? '') }}">
            </div>
            <div class="col-md-6">
                <label for="usuarioApellidos" class="form-label t-obligatorio">Apellidos</label>
                <input type="text" name="last_name" id="usuarioApellidos" class="form-control" maxlength="100" required
                    placeholder="Ingrese los apellidos" value="{{ old('last_name', $usuario->last_name ?? '') }}">
            </div>

            <div class="col-md-6">
                <label for="usuarioDni" class="form-label t-obligatorio">DNI</label>
                <input type="text" name="dni" id="usuarioDni" class="form-control" inputmode="numeric" required
                    maxlength="8" minlength="8" pattern="\d{8}" title="8 dígitos numéricos"
                    placeholder="12345678" value="{{ old('dni', $usuario->dni ?? '') }}">
                <div class="form-text">8 dígitos, único por persona.</div>
            </div>
            <div class="col-md-6">
                <label for="usuarioNacimiento" class="form-label t-obligatorio">Fecha de nacimiento</label>
                <input type="date" name="birthdate" id="usuarioNacimiento" class="form-control" required
                    min="1940-01-02" max="{{ now()->subYears(config('proyecto.personal.edad_minima'))->toDateString() }}"
                    value="{{ old('birthdate', $editando ? $usuario->birthdate?->toDateString() : '') }}">
                <div class="form-text">Debe ser mayor de {{ config('proyecto.personal.edad_minima') }} años.</div>
            </div>

            <div class="col-md-6">
                <label for="usuarioCorreo" class="form-label t-obligatorio">Email</label>
                <input type="email" name="email" id="usuarioCorreo" class="form-control" maxlength="150" required
                    placeholder="correo@municipalidad.gob.pe" value="{{ old('email', $usuario->email ?? '') }}">
            </div>
            <div class="col-md-6">
                <label for="usuarioTelefono" class="form-label">Teléfono</label>
                <input type="text" name="phone" id="usuarioTelefono" class="form-control" inputmode="numeric" maxlength="9"
                    pattern="9\d{8}" title="9 dígitos, empieza con 9"
                    placeholder="987654321" value="{{ old('phone', $usuario->phone ?? '') }}">
                <div class="form-text">Opcional · 9 dígitos.</div>
            </div>

            <div class="col-md-6">
                <label for="usuarioLicencia" class="form-label">Licencia</label>
                <input type="text" name="license" id="usuarioLicencia" class="form-control text-uppercase" maxlength="9"
                    pattern="[A-Za-z][0-9]{8}" title="Una letra seguida de 8 dígitos"
                    placeholder="Q12345678" value="{{ old('license', $usuario->license ?? '') }}">
                <div class="form-text">Opcional · una letra y 8 dígitos.</div>
            </div>
            <div class="col-md-6">
                <label for="usuarioTipo" class="form-label t-obligatorio">Tipo de usuario</label>
                <select name="role" id="usuarioTipo" class="form-select" required @disabled($esPropio)>
                    <option value="">Seleccionar tipo</option>
                    @foreach ($tiposPermitidos as $valor => $texto)
                        <option value="{{ $valor }}" @selected($tipoActual === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                @if ($esPropio)
                    <input type="hidden" name="role" value="{{ $usuario->role }}">
                    <div class="form-text">No puede cambiar su propio tipo de usuario.</div>
                @else
                    <div class="form-text">Administrador: acceso total. Operador: solo lo que se le conceda.</div>
                @endif
            </div>

            <div class="col-12">
                <label for="usuarioDireccion" class="form-label t-obligatorio">Dirección</label>
                <textarea name="address" id="usuarioDireccion" class="form-control" rows="2" minlength="10" maxlength="255" required
                    placeholder="Av. Principal 123, Distrito, Ciudad">{{ old('address', $usuario->address ?? '') }}</textarea>
                <div class="form-text">Dirección completa (mínimo 10 caracteres).</div>
            </div>

            <div class="col-md-6">
                <label for="usuarioClave" class="form-label {{ $editando ? '' : 't-obligatorio' }}">Contraseña</label>
                <input type="password" name="password" id="usuarioClave" class="form-control" maxlength="72" autocomplete="new-password"
                    @required(! $editando) placeholder="{{ $editando ? 'Dejar vacío para no cambiarla' : 'Mínimo 8 caracteres' }}">
                <div class="form-text">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</div>
            </div>
            <div class="col-md-6">
                <label for="usuarioEstado" class="form-label t-obligatorio">Estado</label>
                <select name="status" id="usuarioEstado" class="form-select" required>
                    <option value="1" @selected($estadoActual === '1')>Activo</option>
                    <option value="0" @selected($estadoActual === '0')>Inactivo</option>
                </select>
                <div class="form-text">Solo un usuario activo puede iniciar sesión.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <label class="form-label">Foto de perfil</label>
        <div class="t-caja-foto" id="cajaFoto">
            @if ($fotoActual)
                <div><img src="{{ $fotoActual }}" alt="Foto actual"><small>Foto actual · clic para cambiarla</small></div>
            @else
                <div><i class="bi bi-person-bounding-box"></i><small>Haga clic para elegir una imagen</small></div>
            @endif
        </div>
        <input type="file" name="photo" id="archivoFoto" class="d-none" accept="image/png,image/jpeg,image/webp">
        <div class="form-text">JPG, PNG o WEBP. Máximo 2 MB.</div>
    </div>
</div>

@include('admin.partials.pie-formulario')
