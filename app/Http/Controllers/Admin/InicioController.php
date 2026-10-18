<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contrato;

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

        return view('admin.inicio', [
            'municipalidad' => config('proyecto.municipalidad'),
            // "color" es una de las variantes de .t-funcion-icono definidas en tads.css.
            'funciones' => [
                ['icono' => 'bi-people-fill', 'color' => 'azul', 'texto' => 'Gestión de Personal', 'ruta' => route('admin.personal.index')],
                ['icono' => 'bi-truck', 'color' => 'verde', 'texto' => 'Control de Vehículos', 'ruta' => route('admin.vehiculos.index')],
                ['icono' => 'bi-signpost-split-fill', 'color' => 'ambar', 'texto' => 'Planificación de Rutas', 'ruta' => null],
                ['icono' => 'bi-clock-fill', 'color' => 'turquesa', 'texto' => 'Seguimiento de Asistencia', 'ruta' => route('admin.asistencias.index')],
                ['icono' => 'bi-file-earmark-pdf-fill', 'color' => 'rojo', 'texto' => 'Gestión de Contratos', 'ruta' => route('admin.contratos.index')],
                ['icono' => 'bi-umbrella-fill', 'color' => 'violeta', 'texto' => 'Control de Vacaciones', 'ruta' => null],
            ],
        ]);
    }
}
