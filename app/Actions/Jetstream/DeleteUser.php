<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use App\Services\SeguridadAdministrativa;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        try {
            app(SeguridadAdministrativa::class)->eliminar($user);
        } catch (ValidationException $error) {
            throw ValidationException::withMessages(['password' => $error->validator->errors()->first()]);
        }
    }
}
