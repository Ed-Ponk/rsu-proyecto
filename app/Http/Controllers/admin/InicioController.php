<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;

/**
 * Pantalla de bienvenida (Avance 01): información general del sistema y accesos rápidos a los módulos.
 * Cada función apunta a su módulo; las que todavía no existen se muestran como "Próximamente".
 */
class InicioController extends Controller
{
    public function index()
    {
        // Aprovecha la visita al inicio para cerrar los contratos temporales que ya vencieron.
        Contrato::cerrarVencidos();

        return view('admin.inicio', [
            'municipalidad' => config('proyecto.municipalidad'),
            'funciones' => [
                ['icono' => 'bi-people-fill', 'color' => '#0d3b66', 'texto' => 'Gestión de Personal', 'ruta' => route('admin.personal.index')],
                ['icono' => 'bi-truck', 'color' => '#0a84d6', 'texto' => 'Control de Vehículos', 'ruta' => route('admin.vehiculos.index')],
                ['icono' => 'bi-signpost-split-fill', 'color' => '#f0ad00', 'texto' => 'Planificación de Rutas', 'ruta' => null],
                ['icono' => 'bi-clock-fill', 'color' => '#28a745', 'texto' => 'Seguimiento de Asistencia', 'ruta' => route('admin.asistencias.index')],
                ['icono' => 'bi-file-earmark-pdf-fill', 'color' => '#dc3545', 'texto' => 'Gestión de Contratos', 'ruta' => route('admin.contratos.index')],
                ['icono' => 'bi-umbrella-fill', 'color' => '#6f42c1', 'texto' => 'Control de Vacaciones', 'ruta' => null],
            ],
        ]);
    }
}
