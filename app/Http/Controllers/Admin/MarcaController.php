<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarcaController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.marcas.index');
        }

        $filas = Marca::withCount(['modelos', 'vehiculos'])
            ->orderBy('name')
            ->get()
            ->map(fn (Marca $marca) => $this->fila($marca));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.marcas.create');
    }

    public function store(Request $request)
    {
        $datos = $this->validado($request);

        $marca = $this->conArchivoNuevo($request->file('logo'), 'brand_logo', fn (?string $logo) => Marca::create([
            'name' => $datos['name'],
            'description' => $datos['description'] ?? null,
            'logo' => $logo,
        ]));

        return $this->exito('Marca registrada', 'admin.marcas.index', ['marca' => $this->fila($marca)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.marcas.edit', ['marca' => Marca::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $marca = Marca::findOrFail($id);
        $datos = $this->validado($request, $marca->id);

        $this->conArchivoNuevo($request->file('logo'), 'brand_logo', fn (?string $logo) => $marca->update([
            'name' => $datos['name'],
            'description' => $datos['description'] ?? null,
            'logo' => $logo ?? $marca->logo,
        ]), $marca->logo);

        return $this->exito('Marca actualizada', 'admin.marcas.index', ['marca' => $this->fila($marca->fresh())]);
    }

    public function destroy(string $id)
    {
        $marca = Marca::withCount(['modelos', 'vehiculos'])->findOrFail($id);

        if ($marca->vehiculos_count > 0) {
            return $this->rechazo('No se puede eliminar la marca porque tiene vehículos asignados.', 'admin.marcas.index');
        }

        if ($marca->modelos_count > 0) {
            return $this->rechazo('No se puede eliminar la marca porque tiene modelos. Elimine primero sus modelos.', 'admin.marcas.index');
        }

        $this->borrarArchivo($marca->logo);
        $marca->delete();

        return $this->exito('Marca eliminada', 'admin.marcas.index');
    }

    /** Buscador de marcas (formulario de modelos): por nombre, sin distinguir tildes ni mayúsculas. */
    public function buscar(Request $request)
    {
        $datos = $request->validate(['q' => ['nullable', 'string', 'max:60']]);

        $normalizar = fn (?string $texto) => Str::lower(Str::ascii((string) $texto));
        $palabras = array_filter(preg_split('/\s+/', $normalizar(trim((string) ($datos['q'] ?? '')))));

        $marcas = Marca::withCount('modelos')
            ->orderBy('name')
            ->get()
            ->filter(function (Marca $marca) use ($palabras, $normalizar) {
                $nombre = $normalizar($marca->name);

                foreach ($palabras as $palabra) {
                    if (! str_contains($nombre, $palabra)) {
                        return false;
                    }
                }

                return true;
            })
            ->take(8)
            ->values();

        return response()->json(['data' => $marcas->map(fn (Marca $m) => self::itemDeBuscador($m))]);
    }

    /** Forma que espera el componente Tads.buscador; la usa también el formulario de modelos al editar. */
    public static function itemDeBuscador(Marca $marca): array
    {
        $modelos = $marca->modelos_count ?? $marca->modelos()->count();

        return [
            'id' => $marca->id,
            'titulo' => $marca->name,
            'detalle' => $modelos === 1 ? '1 modelo registrado' : "{$modelos} modelos registrados",
            'foto' => $marca->url_logo,
            'iniciales' => mb_strtoupper(mb_substr($marca->name, 0, 2)),
            'insignia' => null,
        ];
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($ignorarId)],
            'description' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ], [
            'name.unique' => 'Ya existe una marca con ese nombre.',
            'logo.mimes' => 'El logo debe ser JPG, PNG, WEBP, GIF o SVG.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
        ]);
    }

    private function fila(Marca $marca): array
    {
        return [
            'id' => $marca->id,
            'nombre' => $marca->name,
            'descripcion' => $marca->description,
            'logo' => $this->urlArchivo($marca->logo),
            'modelos' => $marca->modelos_count ?? $marca->modelos()->count(),
            'vehiculos' => $marca->vehiculos_count ?? $marca->vehiculos()->count(),
            'creado' => $marca->created_at?->format('d/m/Y H:i'),
            'actualizado' => $marca->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
