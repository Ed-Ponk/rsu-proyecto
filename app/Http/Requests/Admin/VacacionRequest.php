<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Entrada compartida por alta y edición; datos() valida dentro de la transacción del panel. */
class VacacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function datos(?int $idPersonal = null): array
    {
        $request = $this;
        $request->validate([
            'status' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'dni' => ['sometimes', 'nullable', 'string'], 'email' => ['sometimes', 'nullable', 'string'],
            'license' => ['sometimes', 'nullable', 'string'], 'phone' => ['sometimes', 'nullable', 'string'],
            'code' => ['sometimes', 'nullable', 'string'], 'plate' => ['sometimes', 'nullable', 'string'],
        ]);

        $datos = $request->validate([
            'user_id' => $idPersonal ? ['nullable'] : ['required', 'integer', 'exists:users,id'],
            'days' => ['required', 'integer', 'min:1', 'max:'.config('proyecto.vacaciones.dias_por_anio')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'user_id.required' => 'Seleccione al personal.',
            'days.required' => 'Indique los días solicitados.',
            'days.min' => 'Debe solicitar al menos 1 día.',
            'days.max' => 'No puede solicitar más de :max días.',
            'start_date.required' => 'Indique la fecha de inicio.',
        ]);

        if ($idPersonal) {
            $datos['user_id'] = $idPersonal;
        }

        return $datos;
    }
}
