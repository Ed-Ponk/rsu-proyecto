<?php

namespace App\Http\Requests\Admin;

use App\Services\ReglasZona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Entrada compartida por alta y edición; datos() valida dentro de la transacción del panel. */
class ZonaRequest extends FormRequest
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

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('zones', 'name')->ignore($ignorarId)],
            'department' => ['required', 'string', 'max:60'],
            'province' => ['required', 'string', 'max:60'],
            'district' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'average_waste' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'status' => ['required', 'boolean'],
            'coordinates' => ['required', 'array', 'max:200', 'min:'.ReglasZona::MINIMO_PUNTOS],
            'coordinates.*' => ['required', 'array:latitude,longitude'],
            'coordinates.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'coordinates.*.longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'name.unique' => 'Ya existe una zona con ese nombre.',
            'coordinates.required' => 'Dibuje el perímetro de la zona en el mapa (mínimo '.ReglasZona::MINIMO_PUNTOS.' coordenadas).',
            'coordinates.min' => 'El perímetro necesita al menos '.ReglasZona::MINIMO_PUNTOS.' coordenadas.',
            'coordinates.*.latitude.required' => 'Complete la latitud de todas las coordenadas.',
            'coordinates.*.longitude.required' => 'Complete la longitud de todas las coordenadas.',
            'coordinates.*.latitude.numeric' => 'La latitud debe ser un número.',
            'coordinates.*.longitude.numeric' => 'La longitud debe ser un número.',
            'coordinates.*.latitude.between' => 'La latitud debe estar entre -90 y 90.',
            'coordinates.*.longitude.between' => 'La longitud debe estar entre -180 y 180.',
        ]);

        $datos['coordinates'] = array_map(fn ($punto) => [
            'latitude' => round((float) $punto['latitude'], 7), 'longitude' => round((float) $punto['longitude'], 7),
        ], $datos['coordinates']);

        return $datos;
    }
}
