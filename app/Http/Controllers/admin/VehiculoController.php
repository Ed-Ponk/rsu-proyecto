<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\ColorVehiculo;
use App\Models\ImagenVehiculo;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VehiculoController extends Controller
{
    use RespondeAjax;

    /** Relaciones que necesita cada fila del listado. */
    private const CON = ['marca', 'modelo', 'tipo', 'color', 'imagenes'];

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.vehiculos.index');
        }

        $filas = Vehiculo::with(self::CON)
            ->orderBy('code')
            ->get()
            ->map(fn (Vehiculo $vehiculo) => $this->fila($vehiculo));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.vehiculos.create', $this->catalogos());
    }

    public function store(Request $request)
    {
        $vehiculo = Vehiculo::create($this->validado($request));

        return $this->exito('Vehículo registrado', 'admin.vehiculos.index', [
            'vehiculo' => $this->fila($vehiculo->load(self::CON)),
        ]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.vehiculos.edit', $this->catalogos() + ['vehiculo' => Vehiculo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $vehiculo = Vehiculo::findOrFail($id);
        $vehiculo->update($this->validado($request, $vehiculo->id));

        return $this->exito('Vehículo actualizado', 'admin.vehiculos.index', [
            'vehiculo' => $this->fila($vehiculo->fresh(self::CON)),
        ]);
    }

    public function destroy(string $id)
    {
        $vehiculo = Vehiculo::with('imagenes')->findOrFail($id);

        // Avance 3: aquí también se impedirá borrar vehículos que ya estén en una programación.

        $vehiculo->imagenes->each(fn (ImagenVehiculo $imagen) => $this->borrarArchivo($imagen->image));
        $vehiculo->delete();

        return $this->exito('Vehículo eliminado', 'admin.vehiculos.index');
    }

    /* ------------------------------------------------------------------
     |  Imágenes del vehículo
     | ------------------------------------------------------------------ */

    /** Datos que necesita la ventana "Gestión de imágenes". */
    public function imagenes(Vehiculo $vehiculo)
    {
        return response()->json([
            'vehiculo' => [
                'nombre' => $vehiculo->name,
                'codigo' => $vehiculo->code,
                'placa' => $vehiculo->plate,
                'anio' => $vehiculo->year,
            ],
            'imagenes' => $this->listaImagenes($vehiculo->load('imagenes')),
            'maximo' => (int) config('proyecto.vehiculos.max_imagenes'),
        ]);
    }

    /**
     * Aplica de una sola vez lo que el usuario preparó en pantalla:
     *   images[]  archivos nuevos
     *   delete[]  ids de imágenes que ya existían y se quitan
     *   profile   "existing:ID" o "new:N" (N = posición dentro de images[])
     */
    public function guardarImagenes(Request $request, Vehiculo $vehiculo)
    {
        $maxKb = (int) config('proyecto.vehiculos.imagen_max_kb');
        $maxMb = $maxKb / 1024;

        $request->validate([
            'images' => ['nullable', 'array'],
            'images.*' => ['file', 'mimes:'.implode(',', config('proyecto.vehiculos.imagen_formatos')), 'max:'.$maxKb],
            'delete' => ['nullable', 'array'],
            'delete.*' => ['integer'],
            'profile' => ['nullable', 'regex:/^(existing|new):\d+$/'],
        ], [
            'images.*.mimes' => 'Cada imagen debe ser JPEG, PNG, JPG o GIF.',
            'images.*.max' => "Cada imagen puede pesar como máximo {$maxMb} MB.",
            'images.*.file' => 'Uno de los archivos no es válido.',
            'images.*.uploaded' => "No se pudo subir una de las imágenes (máximo {$maxMb} MB).",
        ]);

        $nuevas = array_values($request->file('images', []));
        $aQuitar = array_map('intval', $request->input('delete', []));
        $limite = (int) config('proyecto.vehiculos.max_imagenes');

        $quedarian = $vehiculo->imagenes()->whereNotIn('id', $aQuitar)->count() + count($nuevas);

        if ($quedarian > $limite) {
            throw ValidationException::withMessages(['images' => "Un vehículo admite como máximo {$limite} imágenes."]);
        }

        DB::transaction(function () use ($vehiculo, $nuevas, $aQuitar, $request) {
            $vehiculo->imagenes()->whereIn('id', $aQuitar)->get()->each(function (ImagenVehiculo $imagen) {
                $this->borrarArchivo($imagen->image);
                $imagen->delete();
            });

            $creadas = collect($nuevas)->map(fn ($archivo) => $vehiculo->imagenes()->create([
                'image' => $this->guardarArchivo($archivo, "vehicle_images/{$vehiculo->id}"),
                'profile' => false,
            ]))->all();

            $this->definirPerfil($vehiculo, (string) $request->input('profile'), $creadas);
        });

        return response()->json([
            'mensaje' => 'Imágenes guardadas correctamente',
            'imagenes' => $this->listaImagenes($vehiculo->fresh('imagenes')),
        ]);
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

        $perfil ??= $vehiculo->imagenes()->where('profile', true)->first()
            ?? $vehiculo->imagenes()->reorder('id')->first();

        ImagenVehiculo::where('vehicle_id', $vehiculo->id)->update(['profile' => false]);
        $perfil?->update(['profile' => true]);
    }

    /* ------------------------------------------------------------------ */

    private function catalogos(): array
    {
        return [
            'marcas' => Marca::orderBy('name')->get(['id', 'name']),
            'modelos' => Modelo::orderBy('name')->get(['id', 'name', 'brand_id']),
            'tipos' => TipoVehiculo::orderBy('name')->get(['id', 'name']),
            'colores' => ColorVehiculo::orderBy('name')->get(['id', 'name', 'code']),
        ];
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
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
            'load_capacity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'fuel_capacity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'compact_capacity' => ['required', 'numeric', 'min:0', 'max:999999'],
            'occupant_capacity' => ['required', 'integer', 'between:1,10'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['boolean'],
            'type_id' => ['required', 'exists:vehicletypes,id'],
            'brand_id' => ['required', 'exists:brands,id'],
            // El modelo tiene que pertenecer a la marca elegida.
            'model_id' => ['required', Rule::exists('brandmodels', 'id')->where('brand_id', $request->input('brand_id'))],
            'color_id' => ['required', 'exists:vehiclecolors,id'],
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

    private function fila(Vehiculo $vehiculo): array
    {
        $imagenes = $this->listaImagenes($vehiculo);
        $perfil = collect($imagenes)->firstWhere('perfil', true) ?? ($imagenes[0] ?? null);

        return [
            'id' => $vehiculo->id,
            'nombre' => $vehiculo->name,
            'codigo' => $vehiculo->code,
            'placa' => $vehiculo->plate,
            'anio' => $vehiculo->year,
            'carga' => $vehiculo->load_capacity,
            'activo' => $vehiculo->status,
            'marca' => $vehiculo->marca?->name,
            'modelo' => $vehiculo->modelo?->name,
            'tipo' => $vehiculo->tipo?->name,
            'color' => $vehiculo->color?->name,
            'color_codigo' => $vehiculo->color?->code ?: '#64748B',
            'imagen' => $perfil['url'] ?? asset('images/sin-vehiculo.svg'),
            'total_imagenes' => count($imagenes),
        ];
    }

    private function listaImagenes(Vehiculo $vehiculo): array
    {
        return $vehiculo->imagenes
            ->map(fn (ImagenVehiculo $imagen) => [
                'id' => $imagen->id,
                'url' => $this->urlArchivo($imagen->image, 'images/sin-vehiculo.svg'),
                'perfil' => $imagen->profile,
            ])
            ->values()
            ->all();
    }
}
