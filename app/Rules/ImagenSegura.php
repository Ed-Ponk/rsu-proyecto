<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ImagenSegura implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('El archivo no es una imagen válida.');

            return;
        }
        $dimensiones = @getimagesize($value->getPathname());
        if (! $dimensiones || $dimensiones[0] * $dimensiones[1] > 25_000_000) {
            $fail('La imagen debe ser válida y no superar 25 megapíxeles.');

            return;
        }
        $imagen = @imagecreatefromstring(file_get_contents($value->getPathname()));
        if (! $imagen) {
            $fail('No se pudo decodificar la imagen.');
        } else {
            imagedestroy($imagen);
        }
    }
}
