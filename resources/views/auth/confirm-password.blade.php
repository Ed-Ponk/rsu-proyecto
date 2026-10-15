<x-acceso titulo="Confirmar contraseña">
    <h2>Zona segura</h2>
    <p class="bajada">Confirme su contraseña para continuar.</p>

    <form method="POST" action="{{ route('password.confirm') }}" data-un-envio>
        @csrf

        <div class="campo">
            <label for="clave">Contraseña</label>
            <div class="caja-campo">
                <i class="bi bi-lock icono-campo"></i>
                <input id="clave" type="password" name="password" required autofocus autocomplete="current-password" @class(['invalido' => $errors->has('password')])>
                <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar u ocultar"><i class="bi bi-eye"></i></button>
            </div>
            @error('password')<div class="error-campo"><i class="bi bi-exclamation-circle"></i><span>{{ $message }}</span></div>@enderror
        </div>

        <button type="submit" class="boton-acceso">Confirmar</button>
    </form>
</x-acceso>
