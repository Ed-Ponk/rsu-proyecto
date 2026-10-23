<?php

namespace App\Livewire\Perfil;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;

/** Conserva el formulario Jetstream y actualiza su versión después de guardar. */
class ActualizarInformacion extends UpdateProfileInformationForm
{
    public function updateProfileInformation(UpdatesUserProfileInformation $updater)
    {
        $resultado = parent::updateProfileInformation($updater);
        $this->state['version'] = Auth::user()->fresh()->version;

        return $resultado;
    }
}
