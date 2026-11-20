<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Motivo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Motivos predefinidos para los cambios de programación (Gestión de cambios). */
class MotivoController extends Controller
{
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.motivos.index');
        }

        return response()->json(['data' => Motivo::withCount('cambios')->orderBy('name')->get()->map(fn (Motivo $m) => $this->fila($m))]);
    }

    public function create()
    {
        return view('admin.motivos.create');
    }

    public function store(Request $request)
    {
        $motivo = Motivo::create($this->validado($request));

        return $this->exito('Motivo registrado', 'admin.motivos.index', ['motivo' => $this->fila($motivo)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.motivos.edit', ['motivo' => Motivo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $motivo = Motivo::findOrFail($id);
        $motivo->update($this->validado($request, $motivo->id));

        return $this->exito('Motivo actualizado', 'admin.motivos.index', ['motivo' => $this->fila($motivo->fresh())]);
    }

    public function destroy(string $id)
    {
        $motivo = Motivo::withCount('cambios')->findOrFail($id);
        $motivo->delete();

        // Los cambios ya hechos conservan el nombre del motivo que tenían.
        return $this->exito($motivo->cambios_count
            ? "Motivo eliminado. Los {$motivo->cambios_count} cambio(s) que lo usaban conservan su nombre."
            : 'Motivo eliminado', 'admin.motivos.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('change_reasons', 'name')->ignore($ignorarId)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Ingrese el nombre del motivo.',
            'name.unique' => 'Ya existe un motivo con ese nombre.',
        ]);
    }

    private function fila(Motivo $motivo): array
    {
        return [
            'id' => $motivo->id,
            'nombre' => $motivo->name,
            'descripcion' => $motivo->description,
            'usos' => $motivo->cambios_count ?? $motivo->cambios()->count(),
            'creado' => $motivo->created_at?->format('d/m/Y H:i'),
            'actualizado' => $motivo->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
