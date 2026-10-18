<x-action-section icono="bi-pc-display-horizontal">
    <x-slot name="title">Sesiones del navegador</x-slot>

    <x-slot name="description">Administre y cierre las sesiones abiertas en otros navegadores y dispositivos.</x-slot>

    <x-slot name="content">
        <p class="text-muted small">
            Si es necesario, puede cerrar todas sus otras sesiones abiertas en todos sus dispositivos. Abajo se muestran
            algunas de las más recientes; la lista puede no estar completa. Si cree que su cuenta fue comprometida,
            cambie también su contraseña.
        </p>

        @if (count($this->sessions) > 0)
            <ul class="t-perfil-sesiones">
                @foreach ($this->sessions->take(5) as $session)
                    <li>
                        <span class="t-perfil-sesion-icono"><i class="bi {{ $session->agent->isDesktop() ? 'bi-laptop' : 'bi-phone' }}"></i></span>

                        <div>
                            <strong>{{ $session->agent->platform() ?: 'Desconocido' }} · {{ $session->agent->browser() ?: 'Desconocido' }}</strong>
                            <small>
                                {{ $session->ip_address }} ·
                                @if ($session->is_current_device)
                                    <span class="text-success fw-semibold">Este dispositivo</span>
                                @else
                                    Última actividad {{ $session->last_active }}
                                @endif
                            </small>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if (count($this->sessions) > 5)
                <p class="small text-muted mt-2 mb-0">Y {{ count($this->sessions) - 5 }} sesión(es) más.</p>
            @endif
        @endif

        <div class="d-flex align-items-center gap-3 mt-3">
            <x-button type="button" wire:click="confirmLogout" wire:loading.attr="disabled">
                <i class="bi bi-box-arrow-right me-1"></i> Cerrar las otras sesiones
            </x-button>

            <x-action-message on="loggedOut">Listo.</x-action-message>
        </div>

        <!-- Confirmación -->
        <x-dialog-modal wire:model.live="confirmingLogout">
            <x-slot name="title"><i class="bi bi-box-arrow-right me-2"></i>Cerrar las otras sesiones</x-slot>

            <x-slot name="content">
                <p class="mb-3">Ingrese su contraseña para confirmar que desea cerrar sus otras sesiones abiertas en todos sus dispositivos.</p>

                <div x-data="{}" x-on:confirming-logout-other-browser-sessions.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-input type="password"
                                autocomplete="current-password"
                                placeholder="Contraseña"
                                x-ref="password"
                                wire:model="password"
                                wire:keydown.enter="logoutOtherBrowserSessions" />

                    <x-input-error for="password" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingLogout')" wire:loading.attr="disabled">
                    <i class="bi bi-x-lg me-1"></i> Cancelar
                </x-secondary-button>

                <x-button type="button" class="ms-2" wire:click="logoutOtherBrowserSessions" wire:loading.attr="disabled">
                    <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesiones
                </x-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>
