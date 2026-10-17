<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Panel de Inicio / Centro de Mando Operativo RSU.
 * Entrega métricas operativas de flota, contratos vigentes y personal en turno.
 */
class InicioController extends Controller
{
    public function index()
    {
        // Valores por defecto seguros para prevenir excepciones si la base de datos está vacía o sin migrar
        $kpis = [
            'vehiculos_total' => 0,
            'vehiculos_operativos' => 0,
            'vehiculos_taller' => 0,
            'capacidad_total_toneladas' => 0.0,
            'personal_total' => 0,
            'personal_turno_hoy' => 0,
            'contratos_vigentes' => 0,
            'contratos_por_vencer' => 0,
        ];
        $contratosCriticos = collect();
        $flotaResumen = collect();
        $asistenciasRecientes = collect();

        try {
            // Aprovecha la visita al inicio para cerrar los contratos temporales que ya vencieron.
            Contrato::cerrarVencidos();

            // 1. Contratos críticos (vencen en <= 30 días)
            $contratosCriticos = Contrato::with('personal.tipo')
                ->where('is_active', true)
                ->whereNotNull('end_date')
                ->whereDate('end_date', '>=', today())
                ->whereDate('end_date', '<=', today()->addDays(30))
                ->orderBy('end_date')
                ->get()
                ->map(function ($contrato) {
                    $contrato->dias_restantes = (int) max(0, today()->diffInDays($contrato->end_date, false));

                    return $contrato;
                });

            // 2. Métricas operativas (KPIs)
            $vehiculosTotal = Vehiculo::count();
            $vehiculosOperativos = Vehiculo::where('status', true)->count();
            $vehiculosTaller = Vehiculo::where('status', false)->count();
            $capacidadTotal = (float) (Vehiculo::sum('load_capacity') ?? 0.0);
            $personalTotal = Personal::count();
            $personalTurnoHoy = Personal::asistioEl(today())->count();
            $contratosVigentes = Contrato::vigente()->count();
            $contratosPorVencer = $contratosCriticos->count();

            $kpis = [
                'vehiculos_total' => $vehiculosTotal,
                'vehiculos_operativos' => $vehiculosOperativos,
                'vehiculos_taller' => $vehiculosTaller,
                'capacidad_total_toneladas' => round($capacidadTotal, 2),
                'personal_total' => $personalTotal,
                'personal_turno_hoy' => $personalTurnoHoy,
                'contratos_vigentes' => $contratosVigentes,
                'contratos_por_vencer' => $contratosPorVencer,
            ];

            // 3. Desglose de flota vehicular por tipo
            $flotaResumen = TipoVehiculo::withCount([
                'vehiculos as total',
                'vehiculos as operativos' => fn ($q) => $q->where('status', true),
                'vehiculos as taller' => fn ($q) => $q->where('status', false),
            ])->get();

            if ($flotaResumen->isEmpty() && $vehiculosTotal > 0) {
                $flotaResumen = collect([
                    (object) [
                        'name' => 'Operativos',
                        'total' => $vehiculosOperativos,
                        'operativos' => $vehiculosOperativos,
                        'taller' => 0,
                    ],
                    (object) [
                        'name' => 'En Taller / Inactivos',
                        'total' => $vehiculosTaller,
                        'operativos' => 0,
                        'taller' => $vehiculosTaller,
                    ],
                ]);
            }

            // 4. Marcaciones de asistencia recientes del día
            $asistenciasRecientes = Asistencia::with(['personal.tipo', 'turno'])
                ->whereDate('date', today())
                ->orderByDesc('time')
                ->orderByDesc('id')
                ->take(10)
                ->get();

        } catch (Throwable $e) {
            Log::warning('No se pudieron calcular métricas operativas completas en InicioController: '.$e->getMessage());
        }

        $funciones = [
            ['icono' => 'bi-people-fill', 'color' => '#0d3b66', 'texto' => 'Gestión de Personal', 'ruta' => route('admin.personal.index')],
            ['icono' => 'bi-truck', 'color' => '#0a84d6', 'texto' => 'Control de Vehículos', 'ruta' => route('admin.vehiculos.index')],
            ['icono' => 'bi-signpost-split-fill', 'color' => '#f0ad00', 'texto' => 'Planificación de Rutas', 'ruta' => null],
            ['icono' => 'bi-clock-fill', 'color' => '#28a745', 'texto' => 'Seguimiento de Asistencia', 'ruta' => route('admin.asistencias.index')],
            ['icono' => 'bi-file-earmark-pdf-fill', 'color' => '#dc3545', 'texto' => 'Gestión de Contratos', 'ruta' => route('admin.contratos.index')],
            ['icono' => 'bi-umbrella-fill', 'color' => '#6f42c1', 'texto' => 'Control de Vacaciones', 'ruta' => null],
        ];

        return view('admin.inicio', [
            'municipalidad' => config('proyecto.municipalidad') ?? 'Municipalidad Distrital de José Leonardo Ortiz',
            'kpis' => $kpis,
            'contratos_criticos' => $contratosCriticos,
            'flota_resumen' => $flotaResumen,
            'asistencias_recientes' => $asistenciasRecientes,
            'funciones' => $funciones,
        ]);
    }
}
