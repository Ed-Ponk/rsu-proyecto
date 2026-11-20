<x-form-section submit="updateProfileInformation" icono="bi-person-vcard">
    <x-slot name="title">Información del perfil</x-slot>

    <x-slot name="description">Actualice el nombre y el correo electrónico de su cuenta.</x-slot>

    <x-slot name="form">
        <!-- Foto de perfil -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-12">
                <input type="file" id="photo" class="d-none"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="Foto" />

                <div class="mt-2" x-show="! photoPreview">
                    <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" class="t-avatar t-avatar-lg">
                </div>

                <div class="mt-2" x-show="photoPreview" style="display: none;">
                    <span class="t-avatar t-avatar-lg d-inline-block" x-bind:style="'background-size: cover; background-position: center; background-image: url(\'' + photoPreview + '\');'"></span>
                </div>

                <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    <i class="bi bi-image me-1"></i> Elegir otra foto
                </x-secondary-button>

                @if ($this->user->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        <i class="bi bi-trash3 me-1"></i> Quitar foto
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        <!-- Nombre -->
        <div class="col-12 col-md-8">
            <x-label for="name" value="Nombre" class="t-obligatorio" />
            <x-input id="name" type="text" wire:model="state.name" required autocomplete="name" />
            <x-input-error for="name" />
        </div>

        <!-- Correo -->
        <div class="col-12 col-md-8">
            <x-label for="email" value="Correo electrónico" class="t-obligatorio" />
            <x-input id="email" type="email" wire:model="state.email" required autocomplete="username" />
            <x-input-error for="email" />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <div class="t-nota t-nota-ambar mt-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Su correo todavía no está verificado.

                    <button type="button" class="btn btn-link p-0 align-baseline fw-semibold" wire:click.prevent="sendEmailVerification">
                        Reenviar el correo de verificación
                    </button>
                </div>

                @if ($this->verificationLinkSent)
                    <p class="mt-2 mb-0 small fw-semibold text-success"><i class="bi bi-check-circle-fill me-1"></i>Se envió un nuevo enlace de verificación a su correo.</p>
                @endif
            @endif
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">Cambios guardados.</x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            <i class="bi bi-floppy-fill me-1"></i> Guardar
        </x-button>
    </x-slot>
</x-form-section>
