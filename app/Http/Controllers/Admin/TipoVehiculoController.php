<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuscaEnCatalogo;
use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\TipoVehiculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TipoVehiculoController extends Controller
{
    use BuscaEnCatalogo;
    use RespondeAjax;

    public function index()
    {
        if (! request()->ajax()) {
            return view('admin.tiposvehiculo.index');
        }

        $filas = TipoVehiculo::withCount('vehiculos')
            ->orderBy('name')
            ->get()
            ->map(fn (TipoVehiculo $tipo) => $this->fila($tipo));

        return response()->json(['data' => $filas]);
    }

    /** Buscador del formulario de vehículos. */
    public function buscar(Request $request)
    {
        return $this->respuestaDeBusqueda(
            $request,
            TipoVehiculo::withCount('vehiculos')->orderBy('name')->get(),
            fn (TipoVehiculo $t) => $t->name,
            fn (TipoVehiculo $t) => self::itemDeBuscador($t),
        );
    }

    /** Forma que espera Tads.buscador; también se usa para precargar el valor al editar. */
    public static function itemDeBuscador(TipoVehiculo $tipo): array
    {
        $cantidad = $tipo->vehiculos_count ?? $tipo->vehiculos()->count();

        return [
            'id' => $tipo->id,
            'titulo' => $tipo->name,
            'detalle' => $cantidad === 1 ? '1 vehículo' : "{$cantidad} vehículos",
            'iniciales' => mb_strtoupper(mb_substr($tipo->name, 0, 2)),
            'insignia' => null,
        ];
    }

    public function create()
    {
        return view('admin.tiposvehiculo.create');
    }

    public function store(Request $request)
    {
        $tipo = TipoVehiculo::create($this->validado($request));

        return $this->exito('Tipo de vehículo registrado', 'admin.tiposvehiculo.index', ['tipo' => $this->fila($tipo)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.tiposvehiculo.edit', ['tipo' => TipoVehiculo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $tipo = TipoVehiculo::findOrFail($id);
        $tipo->update($this->validado($request, $tipo->id));

        return $this->exito('Tipo de vehículo actualizado', 'admin.tiposvehiculo.index', ['tipo' => $this->fila($tipo->fresh())]);
    }

    public function destroy(string $id)
    {
        $tipo = TipoVehiculo::withCount('vehiculos')->findOrFail($id);

        if ($tipo->vehiculos_count > 0) {
            return $this->rechazo('No se puede eliminar el tipo porque hay vehículos que lo usan.', 'admin.tiposvehiculo.index');
        }

        $tipo->delete();

        return $this->exito('Tipo de vehículo eliminado', 'admin.tiposvehiculo.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/\p{L}/u', Rule::unique('vehicletypes', 'name')->ignore($ignorarId)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'Ya existe un tipo de vehículo con ese nombre.',
            'name.regex' => 'El nombre debe contener letras (no puede ser solo números o símbolos).',
        ]);
    }

    private function fila(TipoVehiculo $tipo): array
    {
        return [
            'id' => $tipo->id,
            'nombre' => $tipo->name,
            'descripcion' => $tipo->description,
            'vehiculos' => $tipo->vehiculos_count ?? $tipo->vehiculos()->count(),
            'creado' => $tipo->created_at?->format('d/m/Y H:i'),
            'actualizado' => $tipo->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
