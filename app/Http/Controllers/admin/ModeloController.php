<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Marca;
use App\Models\Modelo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModeloController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.modelos.index');
        }

        $filas = Modelo::with('marca')
            ->withCount('vehiculos')
            ->orderBy('name')
            ->get()
            ->map(fn (Modelo $modelo) => $this->fila($modelo));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.modelos.create', ['marcas' => $this->listaDeMarcas()]);
    }

    public function store(Request $request)
    {
        $modelo = Modelo::create($this->validado($request));

        return $this->exito('Modelo registrado', 'admin.modelos.index', ['modelo' => $this->fila($modelo->load('marca'))]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.modelos.edit', [
            'modelo' => Modelo::findOrFail($id),
            'marcas' => $this->listaDeMarcas(),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $modelo = Modelo::findOrFail($id);
        $datos = $this->validado($request, $modelo->id);

        if ((int) $datos['brand_id'] !== $modelo->brand_id && $modelo->vehiculos()->exists()) {
            return $this->rechazo('No se puede cambiar la marca de un modelo que ya tiene vehículos.', 'admin.modelos.index');
        }

        $modelo->update($datos);

        return $this->exito('Modelo actualizado', 'admin.modelos.index', ['modelo' => $this->fila($modelo->fresh('marca'))]);
    }

    public function destroy(string $id)
    {
        $modelo = Modelo::findOrFail($id);

        if ($modelo->vehiculos()->exists()) {
            return $this->rechazo('No se puede eliminar el modelo porque hay vehículos que lo usan.', 'admin.modelos.index');
        }

        $modelo->delete();

        return $this->exito('Modelo eliminado', 'admin.modelos.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'name' => [
                'required', 'string', 'max:100',
                // El nombre solo debe ser único dentro de su marca ("Atego" puede existir en dos marcas distintas).
                Rule::unique('brandmodels', 'name')
                    ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id')))
                    ->ignore($ignorarId),
            ],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('brandmodels', 'code')->ignore($ignorarId)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'brand_id.required' => 'Seleccione la marca del modelo.',
            'name.unique' => 'Esa marca ya tiene un modelo con ese nombre.',
            'code.regex' => 'El código solo admite letras, números, guiones y guiones bajos.',
            'code.unique' => 'Ese código ya lo usa otro modelo.',
        ]);
    }

    private function listaDeMarcas()
    {
        return Marca::orderBy('name')->get(['id', 'name']);
    }

    private function fila(Modelo $modelo): array
    {
        return [
            'id' => $modelo->id,
            'nombre' => $modelo->name,
            'codigo' => $modelo->code,
            'descripcion' => $modelo->description,
            'marca' => $modelo->marca?->name,
            'logo' => $this->urlArchivo($modelo->marca?->logo),
            'vehiculos' => $modelo->vehiculos_count ?? $modelo->vehiculos()->count(),
            'creado' => $modelo->created_at?->format('d/m/Y H:i'),
            'actualizado' => $modelo->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
