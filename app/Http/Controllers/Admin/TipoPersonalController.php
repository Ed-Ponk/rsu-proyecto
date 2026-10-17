<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\TipoPersonal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TipoPersonalController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.tipospersonal.index');
        }

        $filas = TipoPersonal::withCount('personal')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (TipoPersonal $tipo) => $this->fila($tipo));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        return view('admin.tipospersonal.create');
    }

    public function store(Request $request)
    {
        $tipo = TipoPersonal::create($this->validado($request) + ['is_system' => false]);

        return $this->exito('Tipo de personal registrado', 'admin.tipospersonal.index', ['tipo' => $this->fila($tipo)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.tipospersonal.edit', ['tipo' => TipoPersonal::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $tipo = TipoPersonal::findOrFail($id);
        $datos = $this->validado($request, $tipo->id);

        // Conductor y Ayudante conservan su nombre: la programación de rutas depende de él.
        if ($tipo->is_system) {
            unset($datos['name']);
        }

        $tipo->update($datos);

        return $this->exito('Tipo de personal actualizado', 'admin.tipospersonal.index', ['tipo' => $this->fila($tipo->fresh())]);
    }

    public function destroy(string $id)
    {
        $tipo = TipoPersonal::withCount('personal')->findOrFail($id);

        if ($tipo->is_system) {
            return $this->rechazo("«{$tipo->name}» es una función predefinida y no puede eliminarse.", 'admin.tipospersonal.index');
        }

        if ($tipo->personal_count > 0) {
            return $this->rechazo('No se puede eliminar: hay personal registrado con este tipo.', 'admin.tipospersonal.index');
        }

        $tipo->delete();

        return $this->exito('Tipo de personal eliminado', 'admin.tipospersonal.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('usertypes', 'name')->ignore($ignorarId)],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Ya existe un tipo de personal con ese nombre.',
        ]);
    }

    private function fila(TipoPersonal $tipo): array
    {
        return [
            'id' => $tipo->id,
            'nombre' => $tipo->name,
            'descripcion' => $tipo->description,
            'del_sistema' => $tipo->is_system,
            'personal' => $tipo->personal_count ?? $tipo->personal()->count(),
            'creado' => $tipo->created_at?->format('d/m/Y H:i'),
            'actualizado' => $tipo->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
