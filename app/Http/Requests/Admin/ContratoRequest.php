<?php

namespace App\Http\Requests\Admin;

use App\Models\Contrato;
use App\Services\ReglasContrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Entrada compartida por alta y edición; datos() valida dentro de la transacción del panel. */
class ContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function datos(?Contrato $actual = null): array
    {
        $request = $this;
        $request->validate([
            'status' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'dni' => ['sometimes', 'nullable', 'string'], 'email' => ['sometimes', 'nullable', 'string'],
            'license' => ['sometimes', 'nullable', 'string'], 'phone' => ['sometimes', 'nullable', 'string'],
            'code' => ['sometimes', 'nullable', 'string'], 'plate' => ['sometimes', 'nullable', 'string'],
        ]);

        $request->merge([
            'is_active' => $request->boolean('is_active'),
            'probation_months' => $request->filled('probation_months') ? $request->input('probation_months') : null,
        ]);

        // Solo el contrato temporal tiene fecha de término.
        if ($request->input('type') !== Contrato::TEMPORAL) {
            $request->merge(['end_date' => null]);
        }

        $reglas = [
            'type' => ['required', Rule::in(array_keys(Contrato::TIPOS))],
            'start_date' => ['required', 'date_format:Y-m-d', 'after:2000-01-01', 'before_or_equal:'.today()->addYear()->toDateString()],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.today()->addYears(10)->toDateString()],
            'salary' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
            'probation_months' => ['nullable', 'integer', 'between:1,12'],
            'is_active' => ['boolean'],
        ];

        if (! $actual) {
            $reglas['user_id'] = ['required', 'integer', 'min:1', Rule::exists('users', 'id')->whereNotNull('usertype_id')];
        }

        $datos = $request->validate($reglas, [
            'user_id.required' => 'Seleccione a la persona.',
            'salary.gt' => 'El salario debe ser mayor que cero.',
            'probation_months.between' => 'El período de prueba va de 1 a 12 meses.',
        ]);

        $datos['user_id'] = $actual?->user_id ?? $datos['user_id'];

        if ($errores = app(ReglasContrato::class)->revisar($datos, $actual)) {
            throw ValidationException::withMessages($errores);
        }

        return $datos;
    }
}
