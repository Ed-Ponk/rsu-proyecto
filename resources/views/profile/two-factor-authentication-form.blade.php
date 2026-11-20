<x-action-section icono="bi-phone-vibrate">
    <x-slot name="title">Verificación en dos pasos</x-slot>

    <x-slot name="description">Agregue una capa extra de seguridad a su cuenta con un código temporal de su celular.</x-slot>

    <x-slot name="content">
        <h3 class="t-perfil-subtitulo">
            @if ($this->enabled)
                @if ($showingConfirmation)
                    <span class="t-insignia t-ambar me-1">En proceso</span> Termine de activar la verificación en dos pasos.
                @else
                    <span class="t-insignia t-verde me-1">Activada</span> La verificación en dos pasos está activa.
                @endif
            @else
                <span class="t-insignia t-gris me-1">Desactivada</span> Todavía no activó la verificación en dos pasos.
            @endif
        </h3>

        <p class="text-muted small mt-2">
            Al activarla, al iniciar sesión se le pedirá un código seguro y aleatorio que obtiene de una aplicación
            autenticadora en su celular (por ejemplo, Google Authenticator).
        </p>

        @if ($this->enabled)
            @if ($showingQrCode)
                <p class="fw-semibold small mt-3">
                    @if ($showingConfirmation)
                        Para terminar, escanee este código QR con la aplicación autenticadora de su celular, o escriba la clave de configuración, e ingrese el código que le genere.
                    @else
                        La verificación en dos pasos está activa. Escanee este código QR con la aplicación autenticadora de su celular o escriba la clave de configuración.
                    @endif
                </p>

                <div class="t-perfil-qr">
                    {!! $this->user->twoFactorQrCodeSvg() !!}
                </div>

                <p class="small mt-3 mb-0"><span class="text-muted">Clave de configuración:</span>
                    <code class="t-codigo">{{ decrypt($this->user->two_factor_secret) }}</code></p>

                @if ($showingConfirmation)
                    <div class="mt-3 col-md-6 px-0">
                        <x-label for="code" value="Código de la aplicación" class="t-obligatorio" />

                        <x-input id="code" type="text" name="code" inputmode="numeric" autofocus autocomplete="one-time-code"
                            placeholder="123456"
                            wire:model="code"
                            wire:keydown.enter="confirmTwoFactorAuthentication" />

                        <x-input-error for="code" />
                    </div>
                @endif
            @endif

            @if ($showingRecoveryCodes)
                <p class="fw-semibold small mt-3">
                    Guarde estos códigos de recuperación en un lugar seguro. Sirven para recuperar el acceso a su cuenta si pierde el dispositivo de verificación.
                </p>

                <div class="t-perfil-codigos">
                    @foreach (json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)
                        <div>{{ $code }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="mt-4 d-flex flex-wrap gap-2">
            @if (! $this->enabled)
                <x-confirms-password wire:then="enableTwoFactorAuthentication">
                    <x-button type="button" wire:loading.attr="disabled">
                        <i class="bi bi-shield-check me-1"></i> Activar
                    </x-button>
                </x-confirms-password>
            @else
                @if ($showingRecoveryCodes)
                    <x-confirms-password wire:then="regenerateRecoveryCodes">
                        <x-secondary-button>
                            <i class="bi bi-arrow-repeat me-1"></i> Generar nuevos códigos de recuperación
                        </x-secondary-button>
                    </x-confirms-password>
                @elseif ($showingConfirmation)
                    <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                        <x-button type="button" wire:loading.attr="disabled">
                            <i class="bi bi-check2-circle me-1"></i> Confirmar
                        </x-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="showRecoveryCodes">
                        <x-secondary-button>
                            <i class="bi bi-key me-1"></i> Ver códigos de recuperación
                        </x-secondary-button>
                    </x-confirms-password>
                @endif

                @if ($showingConfirmation)
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-secondary-button wire:loading.attr="disabled">
                            <i class="bi bi-x-lg me-1"></i> Cancelar
                        </x-secondary-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-danger-button wire:loading.attr="disabled">
                            <i class="bi bi-shield-x me-1"></i> Desactivar
                        </x-danger-button>
                    </x-confirms-password>
                @endif
            @endif
        </div>
    </x-slot>
</x-action-section>
