<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZonaRequest;
use App\Models\Zona;
use App\Services\ReglasZona;
use App\Services\Ubigeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ZonaController extends Controller
{
    use RespondeAjax;

    public function __construct(private readonly ReglasZona $reglas, private readonly Ubigeo $ubigeo) {}

    public function index()
    {
        if (! request()->routeIs('*.datos') && ! request()->ajax()) {
            return view('admin.zonas.index', ['ubigeo' => $this->ubigeo()]);
        }

        $filas = Zona::withCount('coordenadas')
            ->orderBy('name')
            ->get()
            ->map(fn (Zona $zona) => $this->fila($zona));

        return response()->json(['data' => $filas]);
    }

    /** Todas las zonas con su perímetro, para el explorador "Ver Mapa de Zonas" (con filtros opcionales). */
    public function mapa(Request $request)
    {
        $filtro = $request->validate([
            'department' => ['nullable', 'string', 'max:60'],
            'province' => ['nullable', 'string', 'max:60'],
            'district' => ['nullable', 'string', 'max:80'],
        ]);

        $zonas = Zona::with('coordenadas')
            ->when($filtro['department'] ?? null, fn ($q, $v) => $q->where('department', $v))
            ->when($filtro['province'] ?? null, fn ($q, $v) => $q->where('province', $v))
            ->when($filtro['district'] ?? null, fn ($q, $v) => $q->where('district', $v))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $zonas->map(fn (Zona $zona) => $this->detalle($zona)),
            'resumen' => [
                'zonas' => $zonas->count(),
                'activas' => $zonas->where('status', true)->count(),
                'puntos' => $zonas->sum(fn (Zona $z) => $z->coordenadas->count()),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.zonas.create', $this->catalogos());
    }

    public function store(ZonaRequest $request)
    {
        $datos = $this->validado($request);

        $zona = DB::transaction(function () use ($datos) {
            $zona = Zona::create($this->campos($datos));
            $this->guardarCoordenadas($zona, $datos['coordinates']);

            return $zona;
        });

        return $this->exito('Zona registrada', 'admin.zonas.index', ['zona' => $this->fila($zona->loadCount('coordenadas'))]);
    }

    /** Detalle de una zona (perímetro, área, etc.) para la ventana "Mapa de la zona". */
    public function show(string $id)
    {
        $zona = Zona::with('coordenadas')->findOrFail($id);

        return response()->json($this->detalle($zona));
    }

    public function edit(string $id)
    {
        return view('admin.zonas.edit', ['zona' => Zona::with('coordenadas')->findOrFail($id)] + $this->catalogos());
    }

    public function update(ZonaRequest $request, string $id)
    {
        $zona = Zona::findOrFail($id);
        $datos = $this->validado($request, $zona->id);

        DB::transaction(function () use ($zona, $datos) {
            $zona->update($this->campos($datos));
            $this->guardarCoordenadas($zona, $datos['coordinates']);
        });

        return $this->exito('Zona actualizada', 'admin.zonas.index', ['zona' => $this->fila($zona->fresh()->loadCount('coordenadas'))]);
    }

    public function destroy(string $id)
    {
        Zona::findOrFail($id)->delete();   // sus coordenadas se borran en cascada

        return $this->exito('Zona eliminada', 'admin.zonas.index');
    }

    private function validado(ZonaRequest $request, ?int $ignorarId = null): array
    {
        $datos = $request->datos($ignorarId);
        $this->validarUbicacion($datos['department'], $datos['province'], $datos['district']);
        if ($mensaje = $this->mensajeDelPerimetro($datos['coordinates'], $ignorarId, $datos['department'])) {
            throw ValidationException::withMessages(['coordinates' => $mensaje]);
        }

        return $datos;
    }

    /** Forma del polígono y que no invada otras zonas. */
    private function mensajeDelPerimetro(array $coordenadas, ?int $ignorarId, ?string $departamento = null): ?string
    {
        return $this->reglas->revisarPerimetro($coordenadas, $departamento) ?? $this->reglas->mensajeDeInvasion($coordenadas, $ignorarId);
    }

    /**
     * Puente hacia el buscador de direcciones de OpenStreetMap (Nominatim). El navegador lo intenta primero por su
     * cuenta; si algo se lo impide (red, antivirus, extensiones) la consulta pasa por aquí. El servicio exige
     * identificarse con un User-Agent propio y no más de una consulta por segundo, por eso se guarda en caché.
     */
    public function buscarDireccion(Request $request)
    {
        $datos = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:120'],
            'viewbox' => ['nullable', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
            'bounded' => ['nullable', 'in:0,1'],
        ]);

        $parametros = [
            'format' => 'jsonv2', 'limit' => 6, 'countrycodes' => 'pe', 'accept-language' => 'es',
            'q' => $datos['q'],
        ] + array_filter(['viewbox' => $datos['viewbox'] ?? null, 'bounded' => $datos['bounded'] ?? null], fn ($v) => $v !== null);

        try {
            $lugares = Cache::remember('direccion:'.md5(json_encode($parametros)), now()->addMinutes(10), function () use ($parametros) {
                $respuesta = Http::timeout(8)
                    ->withHeaders(['User-Agent' => config('app.name').' (sistema municipal de residuos; '.config('app.url').')'])
                    ->get('https://nominatim.openstreetmap.org/search', $parametros);

                $respuesta->throw();

                return collect($respuesta->json())->map(fn ($l) => [
                    'display_name' => $l['display_name'] ?? '', 'lat' => $l['lat'] ?? null, 'lon' => $l['lon'] ?? null,
                ])->all();
            });
        } catch (Throwable $e) {
            Log::warning('Falló la consulta de direcciones (Nominatim).', ['error' => $e->getMessage()]);

            return response()->json(['mensaje' => 'El servidor tampoco pudo consultar el buscador de direcciones.'], 502);
        }

        return response()->json(['data' => $lugares]);
    }

    /** Revisión en vivo mientras se dibuja: devuelve el problema del perímetro (o null) sin guardar nada. */
    public function verificar(Request $request)
    {
        $datos = $request->validate([
            'zona' => ['nullable', 'integer'],
            'department' => ['nullable', 'string', 'max:60'],
            'coordinates' => ['nullable', 'array', 'max:200'],
            'coordinates.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'coordinates.*.longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $coordenadas = array_map(fn ($p) => ['latitude' => round((float) $p['latitude'], 7), 'longitude' => round((float) $p['longitude'], 7)], $datos['coordinates'] ?? []);

        // Con menos de 3 puntos todavía se está dibujando: no hay nada que reprochar.
        $mensaje = count($coordenadas) < ReglasZona::MINIMO_PUNTOS
            ? null
            : $this->mensajeDelPerimetro($coordenadas, $datos['zona'] ?? null, $datos['department'] ?? null);

        return response()->json(['mensaje' => $mensaje]);
    }

    private function validarUbicacion(string $departamento, string $provincia, string $distrito): void
    {
        if (! $this->ubigeo->departamentoExiste($departamento)) {
            throw ValidationException::withMessages(['department' => 'Seleccione un departamento de la lista.']);
        }

        if (! $this->ubigeo->provinciaExiste($departamento, $provincia)) {
            throw ValidationException::withMessages(['province' => 'La provincia no pertenece al departamento elegido.']);
        }

        if (! $this->ubigeo->existe($departamento, $provincia, $distrito)) {
            throw ValidationException::withMessages(['district' => 'El distrito no pertenece a la provincia elegida.']);
        }
    }

    private function campos(array $datos): array
    {
        return [
            'name' => trim($datos['name']),
            'department' => $datos['department'],
            'province' => $datos['province'],
            'district' => $datos['district'],
            'description' => filled($datos['description'] ?? null) ? trim($datos['description']) : null,
            'average_waste' => filled($datos['average_waste'] ?? null) ? $datos['average_waste'] : null,
            'status' => (bool) $datos['status'],
        ];
    }

    /** Reemplaza los vértices de la zona respetando el orden en que llegaron. */
    private function guardarCoordenadas(Zona $zona, array $coordenadas): void
    {
        $zona->coordenadas()->delete();

        foreach (array_values($coordenadas) as $posicion => $punto) {
            $zona->coordenadas()->create([
                'latitude' => round((float) $punto['latitude'], 7),
                'longitude' => round((float) $punto['longitude'], 7),
                'position' => $posicion + 1,
            ]);
        }
    }

    /** Catálogo de ubicaciones para los combos en cascada y para centrar el mapa. */
    private function catalogos(): array
    {
        return ['ubigeo' => $this->ubigeo()];
    }

    private function ubigeo(): array
    {
        return [
            'departamentos' => $this->ubigeo->catalogo(),
            'mapa' => config('ubigeo.mapa_inicial'),
            'por_defecto' => config('ubigeo.por_defecto'),
        ];
    }

    private function fila(Zona $zona): array
    {
        return [
            'id' => $zona->id,
            'version' => (int) $zona->version,
            'nombre' => $zona->name,
            'distrito' => $zona->district,
            'provincia' => $zona->province,
            'departamento' => $zona->department,
            'descripcion' => $zona->description,
            'puntos' => $zona->coordenadas_count ?? $zona->coordenadas()->count(),
            'activa' => $zona->status,
            'creado' => $zona->created_at?->format('d/m/Y H:i'),
        ];
    }

    private function detalle(Zona $zona): array
    {
        return [
            'id' => $zona->id,
            'version' => (int) $zona->version,
            'nombre' => $zona->name,
            'distrito' => $zona->district,
            'provincia' => $zona->province,
            'departamento' => $zona->department,
            'descripcion' => $zona->description,
            'residuos' => $zona->average_waste !== null ? (float) $zona->average_waste : null,
            'activa' => $zona->status,
            'area_km2' => round($zona->areaKm2(), 3),
            'puntos' => $zona->coordenadas->count(),
            'vertices' => $zona->vertices(),
        ];
    }
}
