<?php

namespace App\Http\Requests\Admin;

use App\Models\Personal;
use App\Models\TipoPersonal;
use App\Rules\ImagenSegura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Entrada compartida por alta y edición; datos() valida dentro de la transacción del panel. */
class PersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function datos(?Personal $actual = null): array
    {
        $request = $this;
        $request->validate([
            'status' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'dni' => ['sometimes', 'nullable', 'string'], 'email' => ['sometimes', 'nullable', 'string'],
            'license' => ['sometimes', 'nullable', 'string'], 'phone' => ['sometimes', 'nullable', 'string'],
            'code' => ['sometimes', 'nullable', 'string'], 'plate' => ['sometimes', 'nullable', 'string'],
        ]);

        $request->merge([
            'dni' => trim((string) $request->input('dni')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'license' => strtoupper(trim((string) $request->input('license'))) ?: null,
            'phone' => preg_replace('/\s+/', '', (string) $request->input('phone')) ?: null,
            'status' => $request->boolean('status'),
        ]);

        $ignorarId = $actual?->id;
        $esConductor = (int) $request->input('usertype_id') === (int) TipoPersonal::conductor()?->id;
        $edadMinima = (int) config('proyecto.personal.edad_minima');

        $datos = $request->validate([
            'dni' => ['required', 'digits:8', Rule::unique('users', 'dni')->ignore($ignorarId)],
            'usertype_id' => ['required', 'integer', 'min:1', 'exists:usertypes,id'],
            'name' => ['required', 'string', 'max:100', "regex:/^[\pL\s'.\-]+$/u"],
            'last_name' => ['required', 'string', 'max:100', "regex:/^[\pL\s'.\-]+$/u"],
            'birthdate' => ['required', 'date_format:Y-m-d', 'after:1940-01-01', 'before_or_equal:'.now()->subYears($edadMinima)->toDateString()],
            'phone' => ['nullable', 'regex:/^9\d{8}$/'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($ignorarId)],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'license' => [Rule::requiredIf($esConductor), 'nullable', 'regex:'.Personal::FORMATO_LICENCIA, Rule::unique('users', 'license')->ignore($ignorarId)],
            'password' => [$actual ? 'nullable' : 'required', 'string', 'min:6', 'max:60'],
            'status' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', new ImagenSegura, 'max:2048'],
        ], [
            'dni.digits' => 'El DNI debe tener exactamente 8 dígitos.',
            'dni.unique' => 'Ya hay personal registrado con ese DNI.',
            'usertype_id.required' => 'Seleccione el tipo de personal.',
            'name.regex' => 'Los nombres solo pueden tener letras y espacios.',
            'last_name.regex' => 'Los apellidos solo pueden tener letras y espacios.',
            'birthdate.before_or_equal' => "La persona debe ser mayor de {$edadMinima} años.",
            'birthdate.after' => 'La fecha de nacimiento no es válida.',
            'phone.regex' => 'El teléfono tiene 9 dígitos y empieza con 9 (ej. 987654321).',
            'email.unique' => 'Ya hay personal registrado con ese correo.',
            'address.min' => 'La dirección debe tener al menos 10 caracteres.',
            'license.required' => 'La licencia de conducir es obligatoria para los conductores.',
            'license.regex' => 'La licencia es una letra seguida de 8 dígitos (ej. Q12345678).',
            'license.unique' => 'Esa licencia ya está registrada en otra persona.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'photo.image' => 'La foto debe ser una imagen válida.',
            'photo.max' => 'La foto no puede pesar más de 2 MB.',
        ]);

        // La licencia solo existe para los conductores.
        if (! $esConductor) {
            $datos['license'] = null;
        }

        // No se puede inactivar a alguien mientras tenga un contrato vigente.
        if ($actual && ! $datos['status'] && $actual->contratos()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Tiene un contrato vigente. Desactive primero el contrato para poder inactivar a la persona.',
            ]);
        }

        if ($actual && $actual->contratos()->where('start_date', '<', Carbon::parse($datos['birthdate'])->addYears($edadMinima)->toDateString())->exists()) {
            throw ValidationException::withMessages(['birthdate' => 'La nueva fecha deja un contrato anterior a la mayoría de edad.']);
        }

        return $datos;
    }
}
