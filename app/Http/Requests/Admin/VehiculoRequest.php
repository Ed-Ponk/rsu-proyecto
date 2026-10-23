<?php

namespace App\Http\Requests\Admin;

use App\Models\Vehiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Entrada compartida por alta y edición; datos() valida dentro de la transacción del panel. */
class VehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function datos(?int $ignorarId = null): array
    {
        $request = $this;
        $request->validate([
            'status' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'dni' => ['sometimes', 'nullable', 'string'], 'email' => ['sometimes', 'nullable', 'string'],
            'license' => ['sometimes', 'nullable', 'string'], 'phone' => ['sometimes', 'nullable', 'string'],
            'code' => ['sometimes', 'nullable', 'string'], 'plate' => ['sometimes', 'nullable', 'string'],
        ]);

        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
            'plate' => strtoupper(trim((string) $request->input('plate'))),
            'status' => $request->boolean('status'),
        ]);

        $anioMin = (int) config('proyecto.vehiculos.anio_minimo');
        $anioMax = now()->year + 1;

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('vehicles', 'code')->ignore($ignorarId)],
            'plate' => ['required', 'regex:'.Vehiculo::FORMATO_PLACA, Rule::unique('vehicles', 'plate')->ignore($ignorarId)],
            'year' => ['required', 'integer', "between:{$anioMin},{$anioMax}"],
            'load_capacity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999'],
            'fuel_capacity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999'],
            'compact_capacity' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999'],
            'occupant_capacity' => ['required', 'integer', 'between:1,10'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['boolean'],
            'type_id' => ['required', 'integer', 'min:1', 'exists:vehicletypes,id'],
            'brand_id' => ['required', 'integer', 'min:1', 'exists:brands,id'],
            // El modelo tiene que pertenecer a la marca elegida.
            'model_id' => ['required', 'integer', 'min:1', Rule::exists('brandmodels', 'id')->where('brand_id', $request->input('brand_id'))],
            'color_id' => ['required', 'integer', 'min:1', 'exists:vehiclecolors,id'],
        ], [
            'code.regex' => 'El código solo admite letras, números, guiones y guiones bajos.',
            'code.unique' => 'Ya existe un vehículo con ese código.',
            'plate.regex' => 'Formato de placa inválido. Use XXXXXX, XX-XXXX o XXX-XXX.',
            'plate.unique' => 'Ya existe un vehículo con esa placa.',
            'year.between' => "El año debe estar entre {$anioMin} y {$anioMax}.",
            'load_capacity.gt' => 'La capacidad de carga debe ser mayor que cero.',
            'fuel_capacity.gt' => 'La capacidad de combustible debe ser mayor que cero.',
            'occupant_capacity.between' => 'La capacidad de personas debe estar entre 1 y 10.',
            'model_id.exists' => 'El modelo elegido no pertenece a esa marca.',
        ]);
    }
}
