<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Support\DescripcionDeBitacora;
use Illuminate\Http\Request;

/** Quién creó, modificó o eliminó cada registro importante. Solo consulta: la bitácora no se edita. */
class BitacoraController extends Controller
{
    public function index(Request $request)
    {
        $modulos = collect(DescripcionDeBitacora::MODULOS)->map(fn (array $m) => $m[0])->sort()->all();

        if (! $request->ajax()) {
            return view('admin.bitacora.index', ['modelos' => $modulos]);
        }

        $filtro = $request->validate([
            'modelo' => ['nullable', 'in:'.implode(',', array_keys($modulos))],
            'accion' => ['nullable', 'in:creado,modificado,eliminado'],
        ]);

        $texto = new DescripcionDeBitacora;

        $filas = Bitacora::with('usuario')
            ->when($filtro['modelo'] ?? null, fn ($q, $v) => $q->where('model', $v))
            ->when($filtro['accion'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (Bitacora $b) => [
                'id' => $b->id,
                'fecha' => $b->created_at?->format('d/m/Y H:i'),
                'usuario' => $b->usuario?->name ?? 'Sistema',
                'accion' => $b->action,
                'registro' => $texto->registro($b),
                'detalle' => $texto->detalle($b),
            ]);

        return response()->json(['data' => $filas]);
    }
}
