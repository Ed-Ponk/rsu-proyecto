<?php

namespace App\Services;

use App\Models\User;
use App\Support\Bloqueo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SeguridadAdministrativa
{
    public function eliminar(User $usuario): void
    {
        DB::transaction(function () use ($usuario) {
            Bloqueo::tomar('administradores');
            $usuario->refresh();
            $this->exigirOtroActivo($usuario);
            $foto = $usuario->profile_photo_path;
            $usuario->tokens()->delete();
            $usuario->delete();
            DB::afterCommit(function () use ($foto) {
                // deleteProfilePhoto() también guarda el modelo; no debe resucitar una cuenta eliminada.
                try {
                    if (! $foto || Storage::disk(config('jetstream.profile_photo_disk', 'public'))->delete($foto)) {
                        return;
                    }
                } catch (\Throwable) {
                    // La cuenta ya se eliminó: informar la limpieza, no repetir su eliminación.
                }
                Log::warning('No se pudo limpiar la foto de una cuenta eliminada.');
            });
        });
    }

    public function exigirOtroActivo(User $usuario): void
    {
        if ($usuario->usertype_id === null && ! User::whereNull('usertype_id')->where('status', true)->whereKeyNot($usuario->id)->exists()) {
            throw ValidationException::withMessages(['status' => 'Debe quedar al menos un administrador activo.']);
        }
    }
}
