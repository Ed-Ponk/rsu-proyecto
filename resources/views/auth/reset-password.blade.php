<x-acceso titulo="Nueva contraseña">
    <h2>Nueva contraseña</h2>
    <p class="bajada">Elija una contraseña segura para su cuenta.</p>

    <form method="POST" action="{{ route('password.update') }}" data-un-envio>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="campo">
            <label for="correo">Correo electrónico</label>
            <div class="caja-campo">
                <i class="bi bi-envelope icono-campo"></i>
                <input id="correo" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus
                    autocomplete="username" @class(['invalido' => $errors->has('email')])>
            </div>
            @error('email')<div class="error-campo"><i class="bi bi-exclamation-circle"></i><span>{{ $message }}</span></div>@enderror
        </div>

        <div class="campo">
            <label for="clave">Nueva contraseña</label>
            <div class="caja-campo">
                <i class="bi bi-lock icono-campo"></i>
                <input id="clave" type="password" name="password" required autocomplete="new-password" @class(['invalido' => $errors->has('password')])>
                <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar u ocultar"><i class="bi bi-eye"></i></button>
            </div>
            @error('password')<div class="error-campo"><i class="bi bi-exclamation-circle"></i><span>{{ $message }}</span></div>@enderror
        </div>

        <div class="campo">
            <label for="claveConfirmacion">Confirmar contraseña</label>
            <div class="caja-campo">
                <i class="bi bi-lock-fill icono-campo"></i>
                <input id="claveConfirmacion" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>

        <button type="submit" class="boton-acceso">Restablecer contraseña</button>
    </form>
</x-acceso>
