@props(['title' => 'Confirme su contraseña', 'content' => 'Por su seguridad, confirme su contraseña para continuar.', 'button' => 'Confirmar'])

@php
    $confirmableId = md5($attributes->wire('then'));
@endphp

<span
    {{ $attributes->wire('then') }}
    x-data
    x-ref="span"
    x-on:click="$wire.startConfirmingPassword('{{ $confirmableId }}')"
    x-on:password-confirmed.window="setTimeout(() => $event.detail.id === '{{ $confirmableId }}' && $refs.span.dispatchEvent(new CustomEvent('then', { bubbles: false })), 250);"
>
    {{ $slot }}
</span>

@once
<x-dialog-modal wire:model.live="confirmingPassword">
    <x-slot name="title">
        <i class="bi bi-shield-lock-fill me-2"></i>{{ $title }}
    </x-slot>

    <x-slot name="content">
        <p class="mb-3">{{ $content }}</p>

        <div x-data="{}" x-on:confirming-password.window="setTimeout(() => $refs.confirmable_password.focus(), 250)">
            <x-input type="password" placeholder="Contraseña" autocomplete="current-password"
                        x-ref="confirmable_password"
                        wire:model="confirmablePassword"
                        wire:keydown.enter="confirmPassword" />

            <x-input-error for="confirmable_password" class="mt-1" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-secondary-button wire:click="stopConfirmingPassword" wire:loading.attr="disabled">
            <i class="bi bi-x-lg me-1"></i> Cancelar
        </x-secondary-button>

        <x-button class="ms-2" dusk="confirm-password-button" wire:click="confirmPassword" wire:loading.attr="disabled">
            <i class="bi bi-check2-circle me-1"></i> {{ $button }}
        </x-button>
    </x-slot>
</x-dialog-modal>
@endonce
