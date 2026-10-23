<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImagenesRequest;
use App\Http\Requests\Admin\VehiculoRequest;
use App\Models\ColorVehiculo;
use App\Models\ImagenVehiculo;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use App\Services\GaleriaVehiculos;
use Illuminate\Support\Facades\DB;

class VehiculoController extends Controller
{
    use RespondeAjax;

    /** Relaciones que necesita cada fila del listado. */
    private const CON = ['marca', 'modelo', 'tipo', 'color', 'imagenes'];

    public function index()
    {
        if (! request()->routeIs('*.datos') && ! request()->ajax()) {
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

    public function store(VehiculoRequest $request)
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
        $vehiculo = Vehiculo::findOrFail($id);

        return view('admin.vehiculos.edit', ['vehiculo' => $vehiculo, 'elegidos' => $this->elegidos($vehiculo)]);
    }

    public function update(VehiculoRequest $request, string $id)
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

        $rutas = $vehiculo->imagenes->pluck('image')->all();
        $vehiculo->delete();
        DB::afterCommit(fn () => array_map($this->borrarArchivo(...), $rutas));   // primero la base; los archivos solo si el borrado funcionó

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
            'version' => (int) $vehiculo->version,
            'maximo' => (int) config('proyecto.vehiculos.max_imagenes'),
        ]);
    }

    /**
     * Aplica de una sola vez lo que el usuario preparó en pantalla:
     *   images[]  archivos nuevos
     *   delete[]  ids de imágenes que ya existían y se quitan
     *   profile   "existing:ID" o "new:N" (N = posición dentro de images[])
     */
    public function guardarImagenes(ImagenesRequest $request, Vehiculo $vehiculo)
    {
        app(GaleriaVehiculos::class)->guardar($request, $vehiculo);

        return response()->json(['mensaje' => 'Imágenes guardadas correctamente',
            'version' => (int) $vehiculo->fresh()->version,
            'imagenes' => $this->listaImagenes($vehiculo->fresh('imagenes'))]);
    }

    /* ------------------------------------------------------------------ */

    private function catalogos(): array
    {
        return ['elegidos' => $this->elegidos()];
    }

    /**
     * Valores ya elegidos para precargar los buscadores (al editar o al volver con errores).
     * Cada uno tiene la misma estructura que devuelve su buscador, o null si todavía no hay nada elegido.
     */
    private function elegidos(?Vehiculo $vehiculo = null): array
    {
        $id = fn (string $campo) => (int) old($campo, $vehiculo?->{$campo});

        $marca = Marca::withCount('modelos')->find($id('brand_id'));
        $modelo = Modelo::find($id('model_id'));
        $tipo = TipoVehiculo::withCount('vehiculos')->find($id('type_id'));
        $color = ColorVehiculo::find($id('color_id'));

        return [
            'marca' => $marca ? MarcaController::itemDeBuscador($marca) : null,
            'modelo' => $modelo ? ModeloController::itemDeBuscador($modelo) : null,
            'tipo' => $tipo ? TipoVehiculoController::itemDeBuscador($tipo) : null,
            'color' => $color ? ColorController::itemDeBuscador($color) : null,
        ];
    }

    private function validado(VehiculoRequest $request, ?int $ignorarId = null): array
    {
        return $request->datos($ignorarId);
    }

    private function fila(Vehiculo $vehiculo): array
    {
        $imagenes = $this->listaImagenes($vehiculo);
        $perfil = collect($imagenes)->firstWhere('perfil', true) ?? ($imagenes[0] ?? null);

        return [
            'id' => $vehiculo->id,
            'version' => (int) $vehiculo->version,
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
                'version' => (int) $imagen->version,
                'url' => $this->urlArchivo($imagen->image, 'images/sin-vehiculo.svg'),
                'perfil' => $imagen->profile,
            ])
            ->values()
            ->all();
    }
}
