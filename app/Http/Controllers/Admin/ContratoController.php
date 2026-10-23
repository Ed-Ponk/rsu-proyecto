<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContratoRequest;
use App\Models\Contrato;
use App\Services\ReglasContrato;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContratoController extends Controller
{
    use RespondeAjax;

    public function __construct(private readonly ReglasContrato $reglas) {}

    /** Criterios del filtro de fechas: a qué fecha del contrato se aplica el rango. */
    private const CRITERIOS = [
        'vigencia' => 'Vigentes en el periodo',
        'inicio' => 'Fecha de inicio',
        'fin' => 'Fecha de fin',
    ];

    public function index(Request $request)
    {
        // Antes de mostrar nada se cierran los temporales cuya fecha ya pasó.
        Contrato::cerrarVencidos();

        if (! $request->routeIs('*.datos') && ! $request->ajax()) {
            return view('admin.contratos.index', ['criterios' => self::CRITERIOS, 'tipos' => Contrato::TIPOS]);
        }

        $filtro = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'criterio' => ['nullable', Rule::in(array_keys(self::CRITERIOS))],
            'tipo' => ['nullable', Rule::in(array_keys(Contrato::TIPOS))],
        ], [
            'desde.date' => 'La fecha "Desde" no es válida.',
            'hasta.date' => 'La fecha "Hasta" no es válida.',
            'hasta.after_or_equal' => 'La fecha "Hasta" no puede ser anterior a "Desde".',
        ]);

        $desde = $filtro['desde'] ?? null;
        $hasta = $filtro['hasta'] ?? null;

        $filas = Contrato::with('personal.tipo')
            ->when($filtro['tipo'] ?? null, fn ($q, $tipo) => $q->where('type', $tipo))
            ->when($desde || $hasta, fn ($q) => $this->filtrarPorFechas($q, $filtro['criterio'] ?? 'vigencia', $desde, $hasta))
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Contrato $contrato) => $this->fila($contrato));

        return response()->json(['data' => $filas]);
    }

    public function create()
    {
        Contrato::cerrarVencidos();

        return view('admin.contratos.create', $this->catalogos());
    }

    public function store(ContratoRequest $request)
    {
        $contrato = Contrato::create($this->validado($request));

        return $this->exito('Contrato registrado', 'admin.contratos.index', ['contrato' => $this->fila($contrato->load('personal.tipo'))]);
    }

    public function show(string $id)
    {
        abort(404);
    }

    public function edit(string $id)
    {
        return view('admin.contratos.edit', $this->catalogos() + ['contrato' => Contrato::with('personal')->findOrFail($id)]);
    }

    public function update(ContratoRequest $request, string $id)
    {
        $contrato = Contrato::findOrFail($id);
        $datos = $this->validado($request, $contrato);
        unset($datos['user_id']);   // un contrato no se pasa a otra persona

        if ($problema = $this->reglas->revisarDependientes($contrato, $datos + ['end_date' => $datos['end_date'] ?? null])) {
            throw ValidationException::withMessages(['type' => $problema]);
        }

        $contrato->update($datos);

        return $this->exito('Contrato actualizado', 'admin.contratos.index', ['contrato' => $this->fila($contrato->fresh('personal.tipo'))]);
    }

    /** Los contratos no se borran (son historial): se activan o desactivan. */
    public function cambiarEstado(Request $request, string $id)
    {
        $contrato = Contrato::findOrFail($id);

        $request->validate(['is_active' => ['required', 'boolean']]);
        $deseado = $request->boolean('is_active');
        if ($deseado === (bool) $contrato->is_active) {
            return $this->exito('El contrato ya tiene ese estado', 'admin.contratos.index', ['contrato' => $this->fila($contrato)]);
        }

        if ($deseado) {
            $errores = $this->reglas->revisar([
                'user_id' => $contrato->user_id,
                'type' => $contrato->type,
                'start_date' => $contrato->start_date->toDateString(),
                'end_date' => $contrato->end_date?->toDateString(),
                'is_active' => true,
            ], $contrato);

            // Al reactivar solo importan "ya tiene otro activo" y "ya venció".
            if ($bloqueo = array_intersect_key($errores, array_flip(['user_id', 'is_active']))) {
                return $this->rechazo(reset($bloqueo), 'admin.contratos.index');
            }
        }

        // Desactivarlo no puede dejar sin respaldo vacaciones ya solicitadas o aprobadas.
        if ($contrato->is_active && ($problema = $this->reglas->revisarDependientes($contrato, [
            'type' => $contrato->type, 'start_date' => $contrato->start_date->toDateString(),
            'end_date' => $contrato->end_date?->toDateString(), 'is_active' => false,
        ], revisarAsistencias: false))) {
            return $this->rechazo($problema, 'admin.contratos.index');
        }

        $contrato->update(['is_active' => $deseado]);

        return $this->exito(
            $contrato->is_active ? 'Contrato activado' : 'Contrato desactivado',
            'admin.contratos.index',
            ['contrato' => $this->fila($contrato->fresh('personal.tipo'))]
        );
    }

    /** Aplica el rango de fechas según el criterio elegido; "vigencia" incluye los contratos que se cruzan con el periodo. */
    private function filtrarPorFechas($consulta, string $criterio, ?string $desde, ?string $hasta)
    {
        return match ($criterio) {
            'inicio' => $consulta
                ->when($desde, fn ($q) => $q->where('start_date', '>=', $desde))
                ->when($hasta, fn ($q) => $q->where('start_date', '<=', $hasta)),
            'fin' => $consulta->whereNotNull('end_date')
                ->when($desde, fn ($q) => $q->where('end_date', '>=', $desde))
                ->when($hasta, fn ($q) => $q->where('end_date', '<=', $hasta)),
            default => $consulta
                ->when($hasta, fn ($q) => $q->where('start_date', '<=', $hasta))
                ->when($desde, fn ($q) => $q->where(fn ($c) => $c->whereNull('end_date')->orWhere('end_date', '>=', $desde))),
        };
    }

    private function catalogos(): array
    {
        return ['tipos' => Contrato::TIPOS];
    }

    private function validado(ContratoRequest $request, ?Contrato $actual = null): array
    {
        return $request->datos($actual);
    }

    private function fila(Contrato $contrato): array
    {
        $persona = $contrato->personal;

        return [
            'id' => $contrato->id,
            'version' => (int) $contrato->version,
            'dni' => $persona?->dni,
            'persona' => $persona?->nombre_completo,
            'funcion' => $persona?->tipo?->name,
            'tipo' => $contrato->type,
            'tipo_texto' => Contrato::TIPOS[$contrato->type] ?? $contrato->type,
            'inicio' => $contrato->start_date?->format('d/m/Y'),
            'fin' => $contrato->end_date?->format('d/m/Y'),
            'salario' => number_format((float) $contrato->salary, 2),
            'activo' => $contrato->is_active,
            'estado' => $contrato->estado,
        ];
    }
}
