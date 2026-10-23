<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BuscaEnCatalogo;
use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Marca;
use App\Models\Modelo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ModeloController extends Controller
{
    use BuscaEnCatalogo;
    use RespondeAjax;

    public function index()
    {
        if (! request()->routeIs('*.datos') && ! request()->ajax()) {
            return view('admin.modelos.index');
        }

        $filas = Modelo::with('marca')
            ->withCount('vehiculos')
            ->orderBy('name')
            ->get()
            ->map(fn (Modelo $modelo) => $this->fila($modelo));

        return response()->json(['data' => $filas]);
    }

    /** Buscador de modelos del formulario de vehículos: solo los de la marca indicada en ?marca=. */
    public function buscar(Request $request)
    {
        $marca = (int) $request->input('marca');

        return $this->respuestaDeBusqueda(
            $request,
            $marca ? Modelo::where('brand_id', $marca)->orderBy('name')->get() : collect(),
            fn (Modelo $m) => "{$m->name} {$m->code}",
            fn (Modelo $m) => self::itemDeBuscador($m),
        );
    }

    /** Forma que espera Tads.buscador; también se usa para precargar el valor al editar. */
    public static function itemDeBuscador(Modelo $modelo): array
    {
        return [
            'id' => $modelo->id,
            'titulo' => $modelo->name,
            'detalle' => "Código {$modelo->code}",
            'iniciales' => mb_strtoupper(mb_substr($modelo->name, 0, 2)),
            'insignia' => null,
        ];
    }

    public function create()
    {
        return view('admin.modelos.create', ['marcaElegida' => null]);
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
            'modelo' => $modelo = Modelo::with('marca')->findOrFail($id),
            'marcaElegida' => $this->marcaParaBuscador($modelo->marca),
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

    /**
     * Validación en vivo del formulario: aplica las MISMAS reglas que store/update, pero solo a los campos
     * que ya tienen valor, para avisar del duplicado sin esperar a "Guardar". Responde { errores: { campo: mensaje } }.
     */
    public function verificar(Request $request)
    {
        $this->normalizar($request);
        $ignorarId = $request->integer('ignorar') ?: null;

        $campos = array_filter(['brand_id', 'name', 'code'], fn ($campo) => $request->filled($campo));
        $reglas = array_intersect_key($this->reglas($request, $ignorarId), array_flip($campos));

        $validador = Validator::make($request->only($campos), $reglas, $this->mensajes());

        return response()->json([
            'errores' => collect($validador->errors()->messages())->map(fn ($mensajes) => $mensajes[0]),
        ]);
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        $this->normalizar($request);

        return $request->validate($this->reglas($request, $ignorarId), $this->mensajes());
    }

    private function normalizar(Request $request): void
    {
        $request->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $request->input('name'))),
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);
    }

    private function reglas(Request $request, ?int $ignorarId): array
    {
        return [
            'brand_id' => ['required', 'exists:brands,id'],
            'name' => [
                'required', 'string', 'max:100',
                // El nombre solo debe ser único dentro de su marca ("Atego" puede existir en dos marcas distintas).
                Rule::unique('brandmodels', 'name')
                    ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id')))
                    ->ignore($ignorarId),
            ],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/',
                // Igual que el nombre: el código se puede repetir entre marcas, pero no dentro de la misma.
                Rule::unique('brandmodels', 'code')
                    ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id')))
                    ->ignore($ignorarId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'brand_id.required' => 'Seleccione la marca del modelo.',
            'brand_id.exists' => 'La marca elegida no existe.',
            'name.unique' => 'Esa marca ya tiene un modelo con ese nombre.',
            'code.regex' => 'El código solo admite letras, números, guiones y guiones bajos.',
            'code.unique' => 'Esa marca ya tiene un modelo con ese código.',
        ];
    }

    /** Marca ya elegida, con la misma forma que devuelve el buscador de marcas (para precargarlo al editar). */
    private function marcaParaBuscador(?Marca $marca): ?array
    {
        return $marca ? MarcaController::itemDeBuscador($marca->loadCount('modelos')) : null;
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
