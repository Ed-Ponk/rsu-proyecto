@php
    $editando = isset($persona);
    $fotoActual = $editando ? $persona->url_foto : null;
@endphp

<div class="t-alerta-form"></div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="personalDni" class="form-label t-obligatorio">DNI</label>
                <input type="text" name="dni" id="personalDni" class="form-control" inputmode="numeric" required
                    maxlength="8" minlength="8" pattern="\d{8}" title="8 dígitos numéricos"
                    placeholder="12345678" value="{{ old('dni', $persona->dni ?? '') }}">
                <div class="form-text">8 dígitos, único por persona.</div>
            </div>
            <div class="col-md-6">
                <label for="personalTipoTexto" class="form-label t-obligatorio">Tipo de personal</label>
                @include('admin.partials.buscador', [
                    'id' => 'personalTipoTexto', 'nombre' => 'usertype_id', 'url' => route('admin.tipospersonal.buscar'),
                    'placeholder' => 'Escriba el tipo de personal...', 'elegido' => $tipoElegido,
                    'mensaje' => 'Seleccione un tipo de personal de la lista.', 'cambiar' => 'Cambiar tipo',
                    'vacio' => 'No hay tipos de personal registrados.', 'sinCoincidencias' => 'No hay tipos que coincidan con «:texto».',
                ])
                <input type="hidden" id="personalTipo" value="{{ old('usertype_id', $persona->usertype_id ?? '') }}" data-conductor="{{ $idConductor }}">
            </div>

            <div class="col-md-6">
                <label for="personalNombres" class="form-label t-obligatorio">Nombres</label>
                <input type="text" name="name" id="personalNombres" class="form-control" maxlength="100" required
                    placeholder="Ingrese los nombres" value="{{ old('name', $persona->name ?? '') }}">
            </div>
            <div class="col-md-6">
                <label for="personalApellidos" class="form-label t-obligatorio">Apellidos</label>
                <input type="text" name="last_name" id="personalApellidos" class="form-control" maxlength="100" required
                    placeholder="Ingrese los apellidos" value="{{ old('last_name', $persona->last_name ?? '') }}">
            </div>

            <div class="col-md-6">
                <label for="personalNacimiento" class="form-label t-obligatorio">Fecha de nacimiento</label>
                <input type="date" name="birthdate" id="personalNacimiento" class="form-control" required
                    min="1940-01-02" max="{{ now()->subYears(config('proyecto.personal.edad_minima'))->toDateString() }}"
                    value="{{ old('birthdate', $editando ? $persona->birthdate?->toDateString() : '') }}">
                <div class="form-text">Debe ser mayor de {{ config('proyecto.personal.edad_minima') }} años.</div>
            </div>
            <div class="col-md-6" id="bloqueLicencia">
                <label for="personalLicencia" class="form-label t-obligatorio">Licencia de conducir</label>
                <input type="text" name="license" aria-describedby="avisoLicencia" id="personalLicencia" class="form-control text-uppercase" maxlength="9"
                    pattern="[A-Za-z][0-9]{8}" title="Una letra seguida de 8 dígitos"
                    placeholder="Q12345678" value="{{ old('license', $persona->license ?? '') }}">
                <div class="form-text">Obligatoria para conductores (una letra y 8 dígitos).</div>
            </div>

            <div class="col-md-6">
                <label for="personalTelefono" class="form-label">Teléfono</label>
                <input type="text" name="phone" id="personalTelefono" class="form-control" inputmode="numeric" maxlength="9"
                    pattern="9\d{8}" title="9 dígitos, empieza con 9"
                    placeholder="987654321" value="{{ old('phone', $persona->phone ?? '') }}">
                <div class="form-text">Opcional · 9 dígitos.</div>
            </div>
            <div class="col-md-6">
                <label for="personalCorreo" class="form-label t-obligatorio">Email</label>
                <input type="email" name="email" id="personalCorreo" class="form-control" maxlength="150" required
                    placeholder="personal@ejemplo.com" value="{{ old('email', $persona->email ?? '') }}">
            </div>

            <div class="col-md-6">
                <label for="personalEstado" class="form-label t-obligatorio">Estado</label>
                <select name="status" id="personalEstado" class="form-select" required>
                    @php($estadoActual = (string) old('status', ($persona->status ?? true) ? '1' : '0'))
                    <option value="1" @selected($estadoActual === '1')>Activo</option>
                    <option value="0" @selected($estadoActual === '0')>Inactivo</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="personalClave" class="form-label {{ $editando ? '' : 't-obligatorio' }}">Contraseña</label>
                <input type="password" name="password" id="personalClave" class="form-control" autocomplete="new-password"
                    minlength="6" maxlength="60" @required(! $editando)
                    placeholder="{{ $editando ? 'Dejar vacío para no cambiarla' : 'Mínimo 6 caracteres' }}">
                <div class="form-text">Clave personal del trabajador (mínimo 6 caracteres).</div>
            </div>

            <div class="col-12">
                <label for="personalDireccion" class="form-label t-obligatorio">Dirección</label>
                <input type="text" name="address" id="personalDireccion" class="form-control" minlength="10" maxlength="255" required
                    placeholder="Av. Principal 123, Distrito, Ciudad" value="{{ old('address', $persona->address ?? '') }}">
                <div class="form-text">Dirección completa (mínimo 10 caracteres).</div>
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

<p id="avisoLicencia" class="small text-muted">Al cambiar a una función no conductora se eliminará la licencia guardada.</p>
