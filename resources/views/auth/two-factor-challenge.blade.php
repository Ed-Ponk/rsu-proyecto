<x-acceso titulo="Verificación en dos pasos">
    <h2>Verificación en dos pasos</h2>
    <p class="bajada">Ingrese el código de su aplicación de autenticación.</p>

    <form method="POST" action="{{ route('two-factor.login') }}" data-un-envio>
        @csrf

        <div class="campo" id="bloqueCodigo">
            <label for="codigo">Código</label>
            <div class="caja-campo">
                <i class="bi bi-shield-lock icono-campo"></i>
                <input id="codigo" type="text" inputmode="numeric" name="code" autofocus autocomplete="one-time-code" @class(['invalido' => $errors->has('code')])>
            </div>
            @error('code')<div class="error-campo"><i class="bi bi-exclamation-circle"></i><span>{{ $message }}</span></div>@enderror
        </div>

        <div class="campo" id="bloqueRecuperacion" style="display:none">
            <label for="codigoRecuperacion">Código de recuperación</label>
            <div class="caja-campo">
                <i class="bi bi-key icono-campo"></i>
                <input id="codigoRecuperacion" type="text" name="recovery_code" autocomplete="one-time-code" @class(['invalido' => $errors->has('recovery_code')])>
            </div>
            @error('recovery_code')<div class="error-campo"><i class="bi bi-exclamation-circle"></i><span>{{ $message }}</span></div>@enderror
        </div>

        <div class="fila-opciones">
            <a href="#" class="enlace" id="cambiarMetodo">Usar un código de recuperación</a>
        </div>

        <button type="submit" class="boton-acceso">Verificar</button>
    </form>
</x-acceso>
