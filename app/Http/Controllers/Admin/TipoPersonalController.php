<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuscaEnCatalogo;
use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\TipoPersonal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TipoPersonalController extends Controller
{
    use BuscaEnCatalogo;
    use RespondeAjax;

    public function index()
    {
        if (! request()->routeIs('*.datos') && ! request()->ajax()) {
            return view('admin.tipospersonal.index');
        }

        $filas = TipoPersonal::withCount('personal')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (TipoPersonal $tipo) => $this->fila($tipo));

        return response()->json(['data' => $filas]);
    }

    /** Buscador del formulario de personal. */
    public function buscar(Request $request)
    {
        return $this->respuestaDeBusqueda(
            $request,
            TipoPersonal::withCount('personal')->orderByDesc('is_system')->orderBy('name')->get(),
            fn (TipoPersonal $t) => $t->name,
            fn (TipoPersonal $t) => self::itemDeBuscador($t),
        );
    }

    /** Forma que espera Tads.buscador; también se usa para precargar el valor al editar. */
    public static function itemDeBuscador(TipoPersonal $tipo): array
    {
        $cantidad = $tipo->personal_count ?? $tipo->personal()->count();

        return [
            'id' => $tipo->id,
            'titulo' => $tipo->name,
            'detalle' => $cantidad === 1 ? '1 persona' : "{$cantidad} personas",
            'iniciales' => mb_strtoupper(mb_substr($tipo->name, 0, 2)),
            'insignia' => null,
        ];
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
        // Todas las funciones se pueden renombrar: las del sistema se reconocen por su clave interna, no por el nombre.
        $tipo->update($this->validado($request, $tipo->id));

        return $this->exito('Tipo de personal actualizado', 'admin.tipospersonal.index', ['tipo' => $this->fila($tipo->fresh())]);
    }

    public function destroy(string $id)
    {
        $tipo = TipoPersonal::withCount('personal')->findOrFail($id);

        // Conductor y Ayudante son funciones predefinidas: el sistema las usa (licencia, reemplazos, programaciones).
        if ($tipo->is_system) {
            return $this->rechazo('No se puede eliminar: es una función predefinida del sistema (sí se puede renombrar).', 'admin.tipospersonal.index');
        }

        // Integridad: no dejar personal sin función.
        if ($tipo->personal_count > 0) {
            return $this->rechazo('No se puede eliminar: hay personal registrado con este tipo.', 'admin.tipospersonal.index');
        }

        $tipo->delete();

        return $this->exito('Tipo de personal eliminado', 'admin.tipospersonal.index');
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        $request->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100', "regex:/^[\pL\s'.\-]+$/u", Rule::unique('usertypes', 'name')->ignore($ignorarId)],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Ya existe un tipo de personal con ese nombre.',
            'name.min' => 'El nombre debe tener al menos 3 letras.',
            'name.regex' => 'El nombre solo puede tener letras y espacios.',
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
