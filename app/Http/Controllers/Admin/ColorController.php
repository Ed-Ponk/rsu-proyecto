<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuscaEnCatalogo;
use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\ColorVehiculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColorController extends Controller
{
    use BuscaEnCatalogo;
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.colores.index');
        }

        $filas = ColorVehiculo::withCount('vehiculos')
            ->orderBy('name')
            ->get()
            ->map(fn (ColorVehiculo $color) => $this->fila($color));

        return response()->json(['data' => $filas]);
    }

    /** Buscador del formulario de vehículos. */
    public function buscar(Request $request)
    {
        return $this->respuestaDeBusqueda(
            $request,
            ColorVehiculo::orderBy('name')->get(),
            fn (ColorVehiculo $c) => "{$c->name} {$c->code}",
            fn (ColorVehiculo $c) => self::itemDeBuscador($c),
        );
    }

    /** Forma que espera Tads.buscador; "color" pinta la muestra en lugar de una foto. */
    public static function itemDeBuscador(ColorVehiculo $color): array
    {
        return [
            'id' => $color->id,
            'titulo' => $color->name,
            'detalle' => $color->code,
            'color' => $color->code,
            'iniciales' => mb_strtoupper(mb_substr($color->name, 0, 2)),
            'insignia' => null,
        ];
    }

    public function create()
    {
        return view('admin.colores.create');
    }

    public function store(Request $request)
    {
        $color = ColorVehiculo::create($this->validado($request));

        return $this->exito('Color registrado', 'admin.colores.index', ['color' => $this->fila($color)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.colores.edit', ['color' => ColorVehiculo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $color = ColorVehiculo::findOrFail($id);
        $color->update($this->validado($request, $color->id));

        return $this->exito('Color actualizado', 'admin.colores.index', ['color' => $this->fila($color->fresh())]);
    }

    public function destroy(string $id)
    {
        $color = ColorVehiculo::withCount('vehiculos')->findOrFail($id);

        if ($color->vehiculos_count > 0) {
            return $this->rechazo('No se puede eliminar el color porque hay vehículos que lo usan.', 'admin.colores.index');
        }

        $color->delete();

        return $this->exito('Color eliminado', 'admin.colores.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('vehiclecolors', 'name')->ignore($ignorarId)],
            'code' => ['required', 'regex:/^#[0-9A-F]{6}$/', Rule::unique('vehiclecolors', 'code')->ignore($ignorarId)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'Ya existe un color con ese nombre.',
            'code.regex' => 'Escriba un código hexadecimal válido, por ejemplo #104CAD.',
            'code.unique' => 'Ya existe un color con ese código.',
        ]);
    }

    private function fila(ColorVehiculo $color): array
    {
        return [
            'id' => $color->id,
            'nombre' => $color->name,
            'codigo' => $color->code,
            'descripcion' => $color->description,
            'vehiculos' => $color->vehiculos_count ?? $color->vehiculos()->count(),
            'creado' => $color->created_at?->format('d/m/Y H:i'),
            'actualizado' => $color->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
