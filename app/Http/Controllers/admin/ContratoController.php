<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\admin\Concerns\RespondeAjax;
use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Personal;
use App\Services\ReglasContrato;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContratoController extends Controller
{
    use RespondeAjax;

    public function __construct(private readonly ReglasContrato $reglas)
    {
    }

    public function index()
    {
        // Antes de mostrar nada se cierran los temporales cuya fecha ya pasó.
        Contrato::cerrarVencidos();

        if (! request()->ajax()) {
            return view('admin.contratos.index');
        }

        $filas = Contrato::with('personal.tipo')
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

    public function store(Request $request)
    {
        Contrato::cerrarVencidos();

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

    public function update(Request $request, string $id)
    {
        Contrato::cerrarVencidos();

        $contrato = Contrato::findOrFail($id);
        $datos = $this->validado($request, $contrato);
        unset($datos['user_id']);   // un contrato no se pasa a otra persona

        $contrato->update($datos);

        return $this->exito('Contrato actualizado', 'admin.contratos.index', ['contrato' => $this->fila($contrato->fresh('personal.tipo'))]);
    }

    /** Los contratos no se borran (son historial): se activan o desactivan. */
    public function cambiarEstado(string $id)
    {
        Contrato::cerrarVencidos();

        $contrato = Contrato::findOrFail($id);

        if (! $contrato->is_active) {
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

        $contrato->update(['is_active' => ! $contrato->is_active]);

        return $this->exito(
            $contrato->is_active ? 'Contrato activado' : 'Contrato desactivado',
            'admin.contratos.index',
            ['contrato' => $this->fila($contrato->fresh('personal.tipo'))]
        );
    }

    private function catalogos(): array
    {
        return [
            'personal' => Personal::with('tipo')->activos()->orderBy('last_name')->orderBy('name')->get(),
            'tipos' => Contrato::TIPOS,
        ];
    }

    private function validado(Request $request, ?Contrato $actual = null): array
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
            'probation_months' => $request->filled('probation_months') ? $request->input('probation_months') : null,
        ]);

        // Solo el contrato temporal tiene fecha de término.
        if ($request->input('type') !== Contrato::TEMPORAL) {
            $request->merge(['end_date' => null]);
        }

        $reglas = [
            'type' => ['required', Rule::in(array_keys(Contrato::TIPOS))],
            'start_date' => ['required', 'date', 'after:2000-01-01'],
            'end_date' => ['nullable', 'date'],
            'salary' => ['required', 'numeric', 'gt:0', 'max:99999.99'],
            'probation_months' => ['nullable', 'integer', 'between:1,12'],
            'is_active' => ['boolean'],
        ];

        if (! $actual) {
            $reglas['user_id'] = ['required', Rule::exists('users', 'id')->whereNotNull('usertype_id')];
        }

        $datos = $request->validate($reglas, [
            'user_id.required' => 'Seleccione a la persona.',
            'salary.gt' => 'El salario debe ser mayor que cero.',
            'probation_months.between' => 'El período de prueba va de 1 a 12 meses.',
        ]);

        $datos['user_id'] = $actual?->user_id ?? $datos['user_id'];

        if ($errores = $this->reglas->revisar($datos, $actual)) {
            throw ValidationException::withMessages($errores);
        }

        return $datos;
    }

    private function fila(Contrato $contrato): array
    {
        $persona = $contrato->personal;

        return [
            'id' => $contrato->id,
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
