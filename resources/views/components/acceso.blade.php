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

                <div class="pie-tarjeta"><i class="bi bi-shield-check" aria-hidden="true"></i> Sistema de Gestión de Residuos Sólidos Urbanos</div>
            </div>
            <p class="acceso-derechos">© {{ now()->year }} {{ config('proyecto.municipalidad') }}</p>
        </section>

        {{-- Lado izquierdo: presentación institucional --}}
        <aside class="acceso-lateral">
            <img class="acceso-fotografia" src="{{ asset('images/municipalidad.jpg') }}" alt="Palacio municipal de José Leonardo Ortiz">
            <div class="acceso-identidad">
                <span class="acceso-simbolo" aria-hidden="true"><i class="bi bi-recycle"></i></span>
                <span><strong>RSU</strong><span>Gestión municipal</span></span>
            </div>
            <div class="acceso-relato">
                <h1>Cuidar la ciudad.<br>Organizar el día.</h1>
                <p class="bajada-lateral">La flota, las personas y el territorio.<br>Una gestión conectada para José Leonardo Ortiz.</p>
                <div class="acceso-ambitos" aria-label="Áreas del sistema">
                    <span><i class="bi bi-truck" aria-hidden="true"></i> Flota</span>
                    <span><i class="bi bi-people" aria-hidden="true"></i> Personal</span>
                    <span><i class="bi bi-map" aria-hidden="true"></i> Territorio</span>
                </div>
            </div>
            <p class="acceso-lugar"><i class="bi bi-geo-alt" aria-hidden="true"></i> José Leonardo Ortiz · Lambayeque, Perú</p>
        </aside>
    </main>

    <script src="{{ asset('js/tads/auth.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
