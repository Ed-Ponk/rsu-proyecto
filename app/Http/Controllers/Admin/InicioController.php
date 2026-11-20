<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Contrato;
use App\Models\Personal;
use App\Models\Vehiculo;

/**
 * Pantalla de bienvenida: información general del sistema y accesos rápidos a los módulos.
 * Cada función apunta a su módulo; las que todavía no existen se muestran como "Próximamente".
 */
class InicioController extends Controller
{
    public function index()
    {
        // Aprovecha la visita al inicio para cerrar los contratos temporales que ya vencieron.
        Contrato::cerrarVencidos();

        // Accesos rápidos «según los permisos del usuario»: solo se muestran los módulos que puede abrir.
        // "color" es una de las variantes de .t-funcion-icono definidas en tads.css.
        $funciones = collect([
            ['icono' => 'bi-people-fill', 'color' => 'azul', 'texto' => 'Gestión de Personal', 'ruta' => route('admin.personal.index'), 'permiso' => 'employees.view'],
            ['icono' => 'bi-truck', 'color' => 'verde', 'texto' => 'Control de Vehículos', 'ruta' => route('admin.vehiculos.index'), 'permiso' => 'vehicles.view'],
            ['icono' => 'bi-signpost-split-fill', 'color' => 'ambar', 'texto' => 'Planificación de Rutas', 'ruta' => route('admin.programaciones.index'), 'permiso' => 'scheduling.view'],
            ['icono' => 'bi-clock-fill', 'color' => 'turquesa', 'texto' => 'Seguimiento de Asistencia', 'ruta' => route('admin.asistencias.index'), 'permiso' => 'attendances.view'],
            ['icono' => 'bi-file-earmark-pdf-fill', 'color' => 'rojo', 'texto' => 'Gestión de Contratos', 'ruta' => route('admin.contratos.index'), 'permiso' => 'contracts.view'],
            ['icono' => 'bi-airplane-fill', 'color' => 'violeta', 'texto' => 'Control de Vacaciones', 'ruta' => route('admin.vacaciones.index'), 'permiso' => 'vacations.view'],
        ])->filter(fn (array $f) => auth()->user()->can($f['permiso']))->values();

        $indicadores = [];
        if (auth()->user()->esAdministrador()) {
            $vigentes = Contrato::vigente();
            $criticos = (clone $vigentes)->whereBetween('end_date', [today(), today()->addDays(30)])->orderBy('end_date');
            $indicadores = [
                'kpis' => [
                    'vehiculos_total' => Vehiculo::count(),
                    'vehiculos_operativos' => Vehiculo::where('status', true)->count(),
                    'vehiculos_taller' => Vehiculo::where('status', false)->count(),
                    'capacidad_total_toneladas' => Vehiculo::where('status', true)->sum('load_capacity'),
                    'personal_total' => Personal::count(),
                    'personal_turno_hoy' => Asistencia::where('shift_date', today()->toDateString())
                        ->where('type', Asistencia::ENTRADA)->distinct()->count('user_id'),
                    'contratos_vigentes' => $vigentes->count(),
                    'contratos_por_vencer' => (clone $criticos)->count(),
                ],
                'contratos_criticos' => $criticos->limit(10)->get(),
                'flota_resumen' => Vehiculo::orderBy('name')->limit(10)->get(),
                'asistencias_recientes' => Asistencia::orderByDesc('date')->orderByDesc('time')->limit(10)->get(),
            ];
        }

        return view('admin.inicio', [
            'municipalidad' => config('proyecto.municipalidad'),
            'funciones' => $funciones->all(),
        ] + $indicadores);
    }
}
