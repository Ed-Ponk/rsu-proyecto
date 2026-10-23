<x-acceso titulo="Iniciar sesión">
    <h2>Bienvenido de nuevo</h2>
    <p class="bajada">Ingrese a su espacio de gestión municipal.</p>

    @session('status')
        <div class="aviso exito"><i class="bi bi-check-circle-fill"></i><span>{{ $value }}</span></div>
    @endsession

    @if ($errors->any() && ! $errors->hasAny(['email', 'password']))
        <div class="aviso peligro"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <form method="POST" action="{{ route('login') }}" data-un-envio novalidate>
        @csrf

        <div class="campo">
            <label for="correo">Correo electrónico</label>
            <div class="caja-campo">
                <i class="bi bi-envelope icono-campo"></i>
                <input id="correo" type="email" name="email" value="{{ old('email') }}" required autofocus
                    autocomplete="username" placeholder="usuario@municipalidad.gob.pe" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if($errors->has('email')) aria-describedby="error-correo" @endif @class(['invalido' => $errors->has('email')])>
            </div>
            @error('email')
                <div class="error-campo" id="error-correo" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{{ $message }}</span></div>
            @enderror
        </div>

        <div class="campo">
            <label for="clave">Contraseña</label>
            <div class="caja-campo">
                <i class="bi bi-lock icono-campo"></i>
                <input id="clave" type="password" name="password" required autocomplete="current-password"
                    placeholder="Ingrese su contraseña" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if($errors->has('password')) aria-describedby="error-clave" @endif @class(['invalido' => $errors->has('password')])>
                <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar u ocultar contraseña"><i class="bi bi-eye"></i></button>
            </div>
            @error('password')
                <div class="error-campo" id="error-clave" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{{ $message }}</span></div>
            @enderror
        </div>

        <div class="fila-opciones">
            <label class="casilla" for="recordarme">
                <input type="checkbox" id="recordarme" name="remember" value="1" @checked(old('remember'))>
                Mantener sesión activa
            </label>
            @if (Route::has('password.request'))
                <a class="enlace" href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
            @endif
        </div>

        <button type="submit" class="boton-acceso">Iniciar sesión <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
    </form>
</x-acceso>
