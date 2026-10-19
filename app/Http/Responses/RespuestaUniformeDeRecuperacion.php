<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Cuando el correo no existe se responde igual que cuando sí existe ("Le hemos enviado..."), para que nadie pueda
 * averiguar qué correos están registrados probando uno por uno. Los demás fallos (por ejemplo, demasiados intentos)
 * se informan normalmente.
 */
class RespuestaUniformeDeRecuperacion implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status) {}

    public function toResponse($request)
    {
        if ($this->status === Password::INVALID_USER) {
            $mensaje = trans(Password::RESET_LINK_SENT);

            return $request->wantsJson()
                ? new JsonResponse(['message' => $mensaje], 200)
                : back()->with('status', $mensaje);
        }

        throw ValidationException::withMessages(['email' => [trans($this->status)]]);
    }
}
