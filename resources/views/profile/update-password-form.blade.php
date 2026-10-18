<x-form-section submit="updatePassword" icono="bi-key-fill">
    <x-slot name="title">Cambiar contraseña</x-slot>

    <x-slot name="description">Use una contraseña larga y difícil de adivinar para mantener segura su cuenta.</x-slot>

    <x-slot name="form">
        <div class="col-12 col-md-8">
            <x-label for="current_password" value="Contraseña actual" class="t-obligatorio" />
            <x-input id="current_password" type="password" wire:model="state.current_password" autocomplete="current-password" />
            <x-input-error for="current_password" />
        </div>

        <div class="col-12 col-md-8">
            <x-label for="password" value="Nueva contraseña" class="t-obligatorio" />
            <x-input id="password" type="password" wire:model="state.password" autocomplete="new-password" />
            <x-input-error for="password" />
        </div>

        <div class="col-12 col-md-8">
            <x-label for="password_confirmation" value="Confirmar nueva contraseña" class="t-obligatorio" />
            <x-input id="password_confirmation" type="password" wire:model="state.password_confirmation" autocomplete="new-password" />
            <x-input-error for="password_confirmation" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">Contraseña actualizada.</x-action-message>

        <x-button>
            <i class="bi bi-floppy-fill me-1"></i> Guardar
        </x-button>
    </x-slot>
</x-form-section>
