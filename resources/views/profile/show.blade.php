@extends('adminlte::page')

@section('title', 'Mi perfil')

@section('content')
    @php($usuario = auth()->user())

    <div class="t-cabecera">
        <div>
            <h1 class="t-titulo">
                <span class="t-icono-titulo"><i class="bi bi-person-circle"></i></span>
                Mi perfil
            </h1>
            <p class="t-subtitulo">Datos de su cuenta, seguridad y sesiones abiertas.</p>
        </div>
    </div>

    <div class="row g-3">
        {{-- Resumen de la cuenta --}}
        <div class="col-lg-4">
            <aside class="t-tarjeta t-perfil-resumen">
                <div class="t-perfil-portada"></div>
                <div class="t-tarjeta-cuerpo text-center">
                    <span class="t-avatar t-avatar-lg t-perfil-avatar">{{ mb_strtoupper(mb_substr($usuario->name, 0, 1).mb_substr(trim(strstr($usuario->name.' ', ' ')) ?: '', 0, 1)) }}</span>
                    <h2 class="t-perfil-nombre">{{ $usuario->name }}</h2>
                    <p class="t-perfil-correo"><i class="bi bi-envelope me-1"></i>{{ $usuario->email }}</p>

                    <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                        <span class="t-insignia t-azul"><i class="bi bi-shield-fill-check me-1"></i>Administrador</span>
                        @if ($usuario->hasVerifiedEmail())
                            <span class="t-insignia t-verde"><i class="bi bi-patch-check-fill me-1"></i>Correo verificado</span>
                        @else
                            <span class="t-insignia t-ambar"><i class="bi bi-exclamation-triangle-fill me-1"></i>Correo sin verificar</span>
                        @endif
                    </div>

                    <ul class="t-perfil-datos">
                        <li><i class="bi bi-calendar-event"></i><span>Cuenta creada</span><strong>{{ $usuario->created_at?->format('d/m/Y') }}</strong></li>
                        <li><i class="bi bi-phone-vibrate"></i><span>Verificación en dos pasos</span>
                            <strong>{{ $usuario->two_factor_confirmed_at ? 'Activada' : 'Desactivada' }}</strong></li>
                        <li><i class="bi bi-building"></i><span>Entidad</span><strong>{{ config('proyecto.municipalidad') }}</strong></li>
                    </ul>
                </div>
            </aside>
        </div>

        <div class="col-lg-8">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                @livewire('profile.update-password-form')
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                @livewire('profile.two-factor-authentication-form')
            @endif

            @livewire('profile.logout-other-browser-sessions-form')

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                @livewire('profile.delete-user-form')
            @endif
        </div>
    </div>
@stop
