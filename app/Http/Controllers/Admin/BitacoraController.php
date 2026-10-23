<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use Illuminate\Http\Request;

/** Quién creó, modificó o eliminó cada registro importante. Solo consulta: la bitácora no se edita. */
class BitacoraController extends Controller
{
    private const MODELOS = ['Asistencia', 'Contrato', 'Vacacion', 'Turno', 'Zona', 'Feriado', 'Personal', 'User'];

    private const ETIQUETAS = [
        'Asistencia' => 'Asistencia', 'Contrato' => 'Contrato', 'Vacacion' => 'Vacaciones', 'Turno' => 'Turno',
        'Zona' => 'Zona', 'Feriado' => 'Feriado', 'Personal' => 'Personal', 'User' => 'Administrador',
    ];

    public function index(Request $request)
    {
        if (! $request->routeIs('*.datos') && ! $request->ajax()) {
            return view('admin.bitacora.index', ['modelos' => self::ETIQUETAS]);
        }

        $filtro = $request->validate([
            'modelo' => ['nullable', 'in:'.implode(',', self::MODELOS)],
            'accion' => ['nullable', 'in:creado,modificado,eliminado'],
            'draw' => ['nullable', 'integer', 'min:0'], 'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'between:1,100'],
            'search.value' => ['nullable', 'string', 'max:60'],
        ]);

        $consulta = Bitacora::with('usuario')
            ->when($filtro['modelo'] ?? null, fn ($q, $v) => $q->where('model', $v))
            ->when($filtro['accion'] ?? null, fn ($q, $v) => $q->where('action', $v));
        $total = Bitacora::count();
        $filtrados = (clone $consulta)->count();
        $filas = $consulta->orderByDesc('id')
            ->offset($request->has('draw') ? $request->integer('start', 0) : 0)
            ->limit($request->has('draw') ? $request->integer('length', 10) : 1000)
            ->get()
            ->map(fn (Bitacora $b) => [
                'id' => $b->id,
                'fecha' => $b->created_at?->format('d/m/Y H:i'),
                'usuario' => $b->actor_name ?? $b->usuario?->name ?? ($b->user_id ? 'Cuenta eliminada' : 'Sistema / actor histórico no disponible'),
                'accion' => $b->action,
                'registro' => (self::ETIQUETAS[$b->model] ?? $b->model).' #'.$b->model_id,
                'detalle' => $this->detalle($b),
            ]);

        return response()->json(['data' => $filas] + ($request->has('draw') ? ['draw' => $request->integer('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtrados] : ['truncado' => $filas->count() >= 1000]));
    }

    /** Resumen corto de lo que cambió: "campo: antes → después". */
    private function detalle(Bitacora $b): string
    {
        if ($b->action === 'modificado') {
            $partes = [];
            foreach ($b->after ?? [] as $campo => $nuevo) {
                $partes[] = $campo.': '.$this->texto($b->before[$campo] ?? null).' → '.$this->texto($nuevo);
            }

            return mb_strimwidth(implode('; ', $partes), 0, 200, '…');
        }

        return mb_strimwidth(collect($b->action === 'eliminado' ? $b->before : $b->after)
            ->only(['name', 'description', 'date', 'start_date', 'type', 'status', 'time', 'user_id', 'dni'])
            ->map(fn ($v, $k) => $k.': '.$this->texto($v))->implode('; '), 0, 200, '…');
    }

    private function texto(mixed $valor): string
    {
        return is_null($valor) ? '—' : (is_scalar($valor) ? (string) $valor : json_encode($valor));
    }
}
