<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Utilidades comunes a los CRUD del panel: respuestas para AJAX y manejo de archivos subidos. */
trait RespondeAjax
{
    private function esPeticionAjax(): bool
    {
        return request()->ajax() || request()->wantsJson();
    }

    /** Operación exitosa: JSON para el navegador (AJAX) o redirección con mensaje flash. */
    protected function exito(string $mensaje, string $ruta, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($this->esPeticionAjax()) {
            return response()->json(['mensaje' => $mensaje] + $extra);
        }

        return redirect()->route($ruta)->with('success', $mensaje);
    }

    /** Operación rechazada por una regla del negocio (por defecto, 422). */
    protected function rechazo(string $mensaje, string $ruta, int $codigo = 422): JsonResponse|RedirectResponse
    {
        if ($this->esPeticionAjax()) {
            return response()->json(['mensaje' => $mensaje], $codigo);
        }

        return redirect()->route($ruta)->with('error', $mensaje);
    }

    /** Convierte la ruta guardada en la BD en una URL que el navegador pueda abrir. */
    protected function urlArchivo(?string $ruta, string $respaldo = 'images/sin-imagen.svg'): string
    {
        if (blank($ruta) || preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta)) {
            return asset($respaldo);
        }

        return preg_match('#^(https?://|/)#', $ruta) ? $ruta : asset($ruta);
    }

    /**
     * Guarda el archivo dentro de public/uploads/<carpeta> con un nombre único y devuelve la ruta pública.
     * Al no usar storage:link, las imágenes viajan con la carpeta del proyecto a cualquier computadora.
     */
    protected function guardarArchivo(UploadedFile $archivo, string $carpeta): string
    {
        $carpeta = trim($carpeta, '/');
        // La extensión sale del CONTENIDO del archivo, nunca del nombre que manda el cliente.
        $extension = strtolower((string) $archivo->guessExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        if (! in_array($extension, ['jpg', 'png', 'gif', 'webp'], true)) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no es una imagen válida (JPG, PNG, GIF o WEBP).']);
        }

        $nombre = Str::uuid().'.'.$extension;

        $archivo->move(public_path("uploads/{$carpeta}"), $nombre);

        return "/uploads/{$carpeta}/{$nombre}";
    }

    /**
     * Guarda un archivo nuevo y ejecuta $operacion (la escritura en la base de datos). Si esta falla se borra el archivo
     * recién guardado; si funciona se borra el archivo anterior. Así nunca queda un registro apuntando a un archivo inexistente.
     *
     * @template T
     *
     * @param  callable(?string): T  $operacion  recibe la ruta del archivo nuevo (o null si no se subió ninguno)
     * @return T
     */
    protected function conArchivoNuevo(?UploadedFile $archivo, string $carpeta, callable $operacion, ?string $archivoAnterior = null): mixed
    {
        $nueva = $archivo ? $this->guardarArchivo($archivo, $carpeta) : null;

        try {
            $resultado = $operacion($nueva);
        } catch (\Throwable $error) {
            $this->borrarArchivo($nueva);

            throw $error;
        }

        if ($nueva) {
            DB::afterCommit(fn () => $this->borrarArchivo($archivoAnterior));
        }

        return $resultado;
    }

    /** Borra un archivo subido por el sistema (ignora rutas vacías y archivos que no están en /uploads). */
    protected function borrarArchivo(?string $ruta): void
    {
        if (blank($ruta) || ! str_starts_with($ruta, '/uploads/')) {
            return;
        }

        $fisica = public_path(ltrim($ruta, '/'));

        if (is_file($fisica)) {
            if (! @unlink($fisica)) {
                Log::warning('No se pudo limpiar un archivo subido.', ['ruta' => $ruta]);
            }
        }
    }
}
