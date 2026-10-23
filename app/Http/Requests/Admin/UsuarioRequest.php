<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Rules\ClaveCompatible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Entrada compartida por alta y edición; datos() valida dentro de la transacción del panel. */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function datos(?User $actual = null): array
    {
        $request = $this;
        $request->validate([
            'status' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'dni' => ['sometimes', 'nullable', 'string'], 'email' => ['sometimes', 'nullable', 'string'],
            'license' => ['sometimes', 'nullable', 'string'], 'phone' => ['sometimes', 'nullable', 'string'],
            'code' => ['sometimes', 'nullable', 'string'], 'plate' => ['sometimes', 'nullable', 'string'],
        ]);

        $request->merge(['email' => strtolower(trim((string) $request->input('email'))), 'status' => $request->boolean('status')]);

        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($actual?->id)],
            'password' => [$actual ? 'nullable' : 'required', 'string', 'max:72', new ClaveCompatible, 'confirmed', Password::defaults()],
            'status' => ['boolean'],
        ], [
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'name.min' => 'El nombre debe tener al menos 3 caracteres.',
        ]);
    }
}
