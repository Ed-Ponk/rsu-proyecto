<?php

namespace App\Services;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Requests\Admin\ImagenesRequest;
use App\Models\ImagenVehiculo;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GaleriaVehiculos
{
    use RespondeAjax;

    public function guardar(ImagenesRequest $request, Vehiculo $vehiculo)
    {
        $request->datos();

        $nuevas = array_values($request->file('images', []));
        $aQuitar = array_map('intval', $request->input('delete', []));
        $rutasNuevas = [];
        $rutasViejas = [];

        try {
            DB::transaction(function () use ($vehiculo, $nuevas, $aQuitar, $request, &$rutasNuevas, &$rutasViejas) {
                $vehiculo = Vehiculo::whereKey($vehiculo->id)->lockForUpdate()->firstOrFail();
                $limite = (int) config('proyecto.vehiculos.max_imagenes');
                $quedarian = $vehiculo->imagenes()->whereNotIn('id', $aQuitar)->count() + count($nuevas);

                if ($quedarian > $limite) {
                    throw ValidationException::withMessages(['images' => "Un vehículo admite como máximo {$limite} imágenes."]);
                }

                if ($vehiculo->imagenes()->whereIn('id', $aQuitar)->count() !== count($aQuitar)) {
                    throw ValidationException::withMessages(['delete' => 'Una imagen ya no existe o pertenece a otro vehículo.']);
                }
                $vehiculo->imagenes()->whereIn('id', $aQuitar)->get()->each(function (ImagenVehiculo $imagen) use (&$rutasViejas) {
                    $rutasViejas[] = $imagen->image;
                    $imagen->delete();
                });

                $creadas = collect($nuevas)->map(function ($archivo) use ($vehiculo, &$rutasNuevas) {
                    $ruta = $this->guardarArchivo($archivo, "vehicle_images/{$vehiculo->id}");
                    $rutasNuevas[] = $ruta;

                    return $vehiculo->imagenes()->create(['image' => $ruta, 'profile' => false]);
                })->all();

                $this->definirPerfil($vehiculo, (string) $request->input('profile'), $creadas);
                $vehiculo->increment('version');
            });
        } catch (\Throwable $error) {
            array_map($this->borrarArchivo(...), $rutasNuevas);   // la base no cambió: no dejar archivos huérfanos

            throw $error;
        }

        DB::afterCommit(fn () => array_map($this->borrarArchivo(...), $rutasViejas));       // ya confirmado: ahora sí se borran los archivos quitados

    }

    /** Deja exactamente una imagen de perfil: la elegida, la que ya había o, en último caso, la primera. */
    private function definirPerfil(Vehiculo $vehiculo, string $eleccion, array $creadas): void
    {
        $perfil = null;

        if (preg_match('/^(existing|new):(\d+)$/', $eleccion, $partes)) {
            $perfil = $partes[1] === 'new'
                ? ($creadas[(int) $partes[2]] ?? null)
                : $vehiculo->imagenes()->find((int) $partes[2]);
        }

        if ($eleccion !== '' && ! $perfil) {
            throw ValidationException::withMessages(['profile' => 'La imagen elegida ya no está disponible.']);
        }

        $perfil ??= $vehiculo->imagenes()->where('profile', true)->first()
            ?? $vehiculo->imagenes()->reorder('id')->first();

        ImagenVehiculo::where('vehicle_id', $vehiculo->id)->update(['profile' => false]);
        $perfil?->update(['profile' => true]);
    }
}
