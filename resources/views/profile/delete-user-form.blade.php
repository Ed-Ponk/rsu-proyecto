<x-action-section icono="bi-person-x-fill" :peligro="true">
    <x-slot name="title">Eliminar cuenta</x-slot>

    <x-slot name="description">Elimine su cuenta de forma permanente.</x-slot>

    <x-slot name="content">
        <div class="t-nota t-nota-roja">
            <i class="bi bi-exclamation-octagon-fill me-1"></i>
            Al eliminar su cuenta se borrarán de forma permanente todos sus datos. Antes de hacerlo, descargue la información que desee conservar.
        </div>

        <div class="mt-3">
            <x-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                <i class="bi bi-trash3-fill me-1"></i> Eliminar cuenta
            </x-danger-button>
        </div>

        <!-- Confirmación -->
        <x-dialog-modal wire:model.live="confirmingUserDeletion">
            <x-slot name="title"><i class="bi bi-person-x-fill me-2"></i>Eliminar cuenta</x-slot>

            <x-slot name="content">
                <p class="mb-3">
                    ¿Está seguro de que desea eliminar su cuenta? Todos sus datos se borrarán de forma permanente.
                    Ingrese su contraseña para confirmar.
                </p>

                <div x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-input type="password"
                                autocomplete="current-password"
                                placeholder="Contraseña"
                                x-ref="password"
                                wire:model="password"
                                wire:keydown.enter="deleteUser" />

                    <x-input-error for="password" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled">
                    <i class="bi bi-x-lg me-1"></i> Cancelar
                </x-secondary-button>

                <x-danger-button class="ms-2" wire:click="deleteUser" wire:loading.attr="disabled">
                    <i class="bi bi-trash3-fill me-1"></i> Eliminar cuenta
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>
