<?php

namespace App\Http\Requests\Admin;

use App\Rules\ImagenSegura;
use Illuminate\Foundation\Http\FormRequest;

class ImagenesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function datos(): void
    {
        $request = $this;
        $maxKb = (int) config('proyecto.vehiculos.imagen_max_kb');
        $maxMb = $maxKb / 1024;
        $request->validate([
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['file', 'image', new ImagenSegura, 'mimes:'.implode(',', config('proyecto.vehiculos.imagen_formatos')), 'max:'.$maxKb],
            'delete' => ['nullable', 'array', 'max:12'],
            'delete.*' => ['integer', 'min:1', 'distinct'],
            'profile' => ['nullable', 'regex:/^(existing|new):\d+$/'],
        ], [
            'images.*.mimes' => 'Cada imagen debe ser JPEG, PNG, JPG o GIF.',
            'images.*.max' => "Cada imagen puede pesar como máximo {$maxMb} MB.",
            'images.*.file' => 'Uno de los archivos no es válido.',
            'images.*.uploaded' => "No se pudo subir una de las imágenes (máximo {$maxMb} MB).",
        ]);
    }
}
