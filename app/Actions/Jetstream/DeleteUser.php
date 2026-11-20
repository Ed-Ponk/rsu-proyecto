<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        // Sin administradores nadie podría volver a entrar al sistema.
        if ($user->usertype_id === null && User::whereNull('usertype_id')->count() <= 1) {
            throw ValidationException::withMessages(['password' => 'No se puede eliminar la única cuenta de administrador del sistema.']);
        }

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
