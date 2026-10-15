<x-acceso titulo="Recuperar contraseña">
    <h2>Recuperar contraseña</h2>
    <p class="bajada">Escriba su correo y le enviaremos un enlace para crear una contraseña nueva.</p>

    @session('status')
        <div class="aviso exito"><i class="bi bi-check-circle-fill"></i><span>{{ $value }}</span></div>
    @endsession

    <form method="POST" action="{{ route('password.email') }}" data-un-envio>
        @csrf

        <div class="campo">
            <label for="correo">Correo electrónico</label>
            <div class="caja-campo">
                <i class="bi bi-envelope icono-campo"></i>
                <input id="correo" type="email" name="email" value="{{ old('email') }}" required autofocus
                    autocomplete="username" @class(['invalido' => $errors->has('email')])>
            </div>
            @error('email')
                <div class="error-campo"><i class="bi bi-exclamation-circle"></i><span>{{ $message }}</span></div>
            @enderror
        </div>

        <button type="submit" class="boton-acceso">Enviar enlace</button>
        <div class="pie-tarjeta"><a class="enlace" href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Volver a iniciar sesión</a></div>
    </form>
</x-acceso>
