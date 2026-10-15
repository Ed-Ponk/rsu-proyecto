@props(['titulo' => 'Acceso'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titulo }} · {{ config('proyecto.municipalidad') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-municipalidad.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fonts/source-sans-3/index.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/font/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tads-login.css') }}">
</head>
<body class="acceso">
    <main class="acceso-contenedor">
        {{-- Lado derecho: logo y formulario (el CSS lo coloca a la derecha) --}}
        <section class="acceso-formulario">
            <div class="acceso-bloque">
                <div class="logo-tarjeta">
                    <img src="{{ asset('images/logo-municipalidad.svg') }}" alt="Municipalidad Distrital de José Leonardo Ortiz">
                </div>

                {{ $slot }}

                <div class="pie-tarjeta">Sistema de Gestión de Residuos Sólidos Urbanos</div>
            </div>
        </section>

        {{-- Lado izquierdo: presentación institucional --}}
        <aside class="acceso-lateral">
            <span class="etiqueta"><i class="bi bi-building-fill me-1"></i> Gobierno local</span>
            <h1>Una ciudad más limpia empieza con una buena gestión</h1>
            <p class="bajada-lateral">Administre la flota, el personal de recolección y su asistencia diaria desde un solo sistema.</p>

            <ul class="acceso-pasos">
                <li><i class="bi bi-truck-front"></i> Control de vehículos y sus imágenes</li>
                <li><i class="bi bi-people"></i> Personal, contratos y asistencia</li>
                <li><i class="bi bi-geo-alt"></i> Zonas, rutas y programación</li>
            </ul>

            <div class="acceso-sellos">
                <span class="eco"><i class="bi bi-leaf-fill me-1"></i> Eco-Friendly</span>
                <span class="sostenible"><i class="bi bi-recycle me-1"></i> Sostenible</span>
                <span class="responsable"><i class="bi bi-heart-fill me-1"></i> Responsable</span>
            </div>

            <p class="acceso-derechos">© {{ now()->year }} {{ config('proyecto.municipalidad') }}</p>
        </aside>
    </main>

    <script>
        // Botón del "ojo": muestra u oculta la contraseña del campo indicado en data-ver-clave.
        document.querySelectorAll('[data-ver-clave]').forEach((boton) => {
            boton.addEventListener('click', () => {
                const campo = document.getElementById(boton.dataset.verClave);
                const mostrar = campo.type === 'password';
                campo.type = mostrar ? 'text' : 'password';
                boton.innerHTML = `<i class="bi ${mostrar ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
            });
        });

        // Evita enviar dos veces el mismo formulario.
        document.querySelectorAll('form[data-un-envio]').forEach((formulario) => {
            formulario.addEventListener('submit', () => {
                const boton = formulario.querySelector('button[type=submit]');
                if (boton) { boton.disabled = true; boton.textContent = 'Procesando...'; }
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
