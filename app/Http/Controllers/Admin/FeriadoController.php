<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Feriado;
use App\Services\CargadorFeriados;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeriadoController extends Controller
{
    use RespondeAjax;

    public function index(Request $request)
    {
        if (! $request->ajax()) {
            return view('admin.feriados.index', ['resumen' => $this->resumen()]);
        }

        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'estado' => ['nullable', 'in:activo,inactivo'],
        ], ['hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.']);

        $filas = Feriado::query()
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('date', '>=', $request->input('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('date', '<=', $request->input('hasta')))
            ->when($request->input('estado') === 'activo', fn ($q) => $q->where('status', true))
            ->when($request->input('estado') === 'inactivo', fn ($q) => $q->where('status', false))
            ->orderBy('date')
            ->get()
            ->map(fn (Feriado $feriado) => $this->fila($feriado));

        return response()->json(['data' => $filas, 'resumen' => $this->resumen()]);
    }

    public function create()
    {
        return view('admin.feriados.create');
    }

    public function store(Request $request)
    {
        $feriado = Feriado::create($this->validado($request));

        return $this->exito('Feriado registrado', 'admin.feriados.index', ['feriado' => $this->fila($feriado)]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.feriados.edit', ['feriado' => Feriado::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $feriado = Feriado::findOrFail($id);
        $feriado->update($this->validado($request, $feriado->id));

        return $this->exito('Feriado actualizado', 'admin.feriados.index', ['feriado' => $this->fila($feriado->fresh())]);
    }

    public function destroy(string $id)
    {
        Feriado::findOrFail($id)->delete();

        return $this->exito('Feriado eliminado', 'admin.feriados.index');
    }

    /** Paso 1 de la carga con barra de progreso: consulta la fuente y devuelve los feriados sin guardarlos. */
    public function consultar(CargadorFeriados $cargador)
    {
        $anio = today()->year;
        [$feriados, $origen] = $cargador->obtener($anio);

        return response()->json([
            'anio' => $anio,
            'origen' => $origen,
            'fuente' => CargadorFeriados::FUENTES[$origen],
            'feriados' => collect($feriados)->map(fn ($descripcion, $fecha) => ['date' => $fecha, 'description' => $descripcion])->values(),
        ]);
    }

    /**
     * Botón "Cargar Feriados Perú". Con `feriados` guarda ese lote (así la pantalla puede mostrar el avance);
     * sin él consulta la fuente y guarda todo el año de una vez.
     */
    public function cargar(Request $request, CargadorFeriados $cargador)
    {
        if ($request->has('feriados')) {
            $lote = $request->validate([
                'feriados' => ['required', 'array', 'max:60'],
                'feriados.*.date' => ['required', 'date_format:Y-m-d'],
                'feriados.*.description' => ['required', 'string', 'max:150'],
            ]);

            $resultado = $cargador->guardar(collect($lote['feriados'])->pluck('description', 'date')->all());

            return $this->exito('Lote guardado', 'admin.feriados.index', $resultado + ['resumen' => $this->resumen()]);
        }

        $anio = today()->year;
        $resultado = $cargador->cargar($anio);

        $mensaje = $resultado['creados'] === 0
            ? "Los feriados de {$anio} ya estaban cargados."
            : "Se cargaron {$resultado['creados']} feriado(s) de {$anio}"
                .($resultado['existentes'] > 0 ? "; {$resultado['existentes']} ya existían." : '.');

        if ($resultado['origen'] === 'local') {
            $mensaje .= ' (Sin conexión a la API: se usó el calendario del sistema).';
        }

        return $this->exito($mensaje, 'admin.feriados.index', $resultado + ['resumen' => $this->resumen()]);
    }

    private function validado(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate([
            'date' => [
                'required', 'date',
                'after_or_equal:'.config('proyecto.feriados.anio_minimo').'-01-01',
                'before_or_equal:'.(today()->year + config('proyecto.feriados.anios_hacia_adelante')).'-12-31',
                Rule::unique('holidays', 'date')->ignore($ignorarId),
            ],
            'description' => ['required', 'string', 'min:3', 'max:150'],
            'status' => ['required', 'boolean'],
        ], [
            'date.unique' => 'Ya hay un feriado registrado en esa fecha.',
            'date.after_or_equal' => 'La fecha no puede ser anterior al '.config('proyecto.feriados.anio_minimo').'.',
            'date.before_or_equal' => 'La fecha no puede pasar de '.(today()->year + config('proyecto.feriados.anios_hacia_adelante')).'.',
            'description.min' => 'La descripción debe tener al menos 3 caracteres.',
        ]);

        $datos['description'] = trim($datos['description']);

        return $datos;
    }

    private function resumen(): array
    {
        return [
            'total' => Feriado::count(),
            'activos' => Feriado::activo()->count(),
            'proximos' => Feriado::activo()->whereDate('date', '>=', today())->count(),
            'anio' => today()->year,
        ];
    }

    private function fila(Feriado $feriado): array
    {
        return [
            'id' => $feriado->id,
            'fecha' => $feriado->date->format('d/m/Y'),
            'orden' => $feriado->date->toDateString(),
            'descripcion' => $feriado->description,
            'activo' => $feriado->status,
            'dia' => $feriado->dia_semana,
            'creado' => $feriado->created_at?->format('d/m/Y H:i'),
            'actualizado' => $feriado->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
