<?php

namespace App\Support;

/**
 * Catálogo de permisos del panel (Avance 05). Cada permiso tiene un código `recurso.accion` (ej. colors.view) y se
 * agrupa por módulo y recurso, igual que la pantalla «Permisos de Usuario».
 *
 * También sabe qué permiso exige cada ruta del panel, para que un único middleware proteja todo el sistema.
 */
final class CatalogoPermisos
{
    public const VER = 'view';

    public const CREAR = 'create';

    public const EDITAR = 'edit';

    public const ELIMINAR = 'delete';

    public const EXTRA = 'extra';

    /** Permiso comodín: basta con tener cualquier permiso (lo usan los buscadores que alimentan los formularios). */
    public const CUALQUIERA = '*';

    /**
     * Los cuatro permisos de un CRUD. Los textos varían según el recurso: «Ver Colores», «Crear Colores»...
     *
     * @param  array<string, string>  $textos  sobrescribe el texto de una acción (view|create|edit|delete)
     */
    private static function crud(string $codigo, string $nombre, array $textos = [], array $extras = []): array
    {
        return [
            ['codigo' => "$codigo.view", 'texto' => $textos['view'] ?? "Ver $nombre", 'tipo' => self::VER],
            ['codigo' => "$codigo.create", 'texto' => $textos['create'] ?? "Crear $nombre", 'tipo' => self::CREAR],
            ['codigo' => "$codigo.edit", 'texto' => $textos['edit'] ?? "Editar $nombre", 'tipo' => self::EDITAR],
            ['codigo' => "$codigo.delete", 'texto' => $textos['delete'] ?? "Eliminar $nombre", 'tipo' => self::ELIMINAR],
            ...$extras,
        ];
    }

    private static function extra(string $codigo, string $texto): array
    {
        return ['codigo' => $codigo, 'texto' => $texto, 'tipo' => self::EXTRA];
    }

    /** @return array<int, array{clave:string,titulo:string,icono:string,recursos:array}> */
    public static function modulos(): array
    {
        static $modulos = null;

        return $modulos ??= [
            [
                'clave' => 'usuarios', 'titulo' => 'Gestión de Usuarios', 'icono' => 'bi-people-fill',
                'recursos' => [
                    ['titulo' => 'Usuarios', 'icono' => 'bi-people-fill', 'permisos' => self::crud('users', 'Usuarios', extras: [
                        self::extra('users.manage', 'Gestionar Permisos de Usuarios'),
                    ])],
                ],
            ],
            [
                'clave' => 'vehiculos', 'titulo' => 'Gestión de Vehículos', 'icono' => 'bi-truck',
                'recursos' => [
                    ['titulo' => 'Colores', 'icono' => 'bi-palette-fill', 'permisos' => self::crud('colors', 'Colores')],
                    ['titulo' => 'Marcas', 'icono' => 'bi-tags-fill', 'permisos' => self::crud('brands', 'Marcas')],
                    ['titulo' => 'Modelos', 'icono' => 'bi-car-front-fill', 'permisos' => self::crud('brandmodels', 'Modelos')],
                    ['titulo' => 'Tipos de Vehículos', 'icono' => 'bi-truck-flatbed', 'permisos' => self::crud('vehicletypes', 'Tipos de Vehículos')],
                    ['titulo' => 'Vehículos', 'icono' => 'bi-truck-front-fill', 'permisos' => self::crud('vehicles', 'Vehículos', extras: [
                        self::extra('vehicles.manage-images', 'Gestionar Imágenes de Vehículos'),
                    ])],
                ],
            ],
            [
                'clave' => 'personal', 'titulo' => 'Gestión de Personal', 'icono' => 'bi-people',
                'recursos' => [
                    ['titulo' => 'Tipos de Personal', 'icono' => 'bi-person-badge-fill', 'permisos' => self::crud('employeetypes', 'Tipos de Personal')],
                    ['titulo' => 'Personal', 'icono' => 'bi-person-lines-fill', 'permisos' => self::crud('employees', 'Personal')],
                    ['titulo' => 'Contratos', 'icono' => 'bi-file-earmark-text-fill', 'permisos' => self::crud('contracts', 'Contratos')],
                    ['titulo' => 'Asistencia', 'icono' => 'bi-calendar-check-fill', 'permisos' => self::crud('attendances', 'Asistencia', ['create' => 'Registrar Asistencia'])],
                    ['titulo' => 'Vacaciones', 'icono' => 'bi-airplane-fill', 'permisos' => self::crud('vacations', 'Vacaciones')],
                ],
            ],
            [
                'clave' => 'programacion', 'titulo' => 'Gestión de Programación', 'icono' => 'bi-calendar3',
                'recursos' => [
                    ['titulo' => 'Turnos', 'icono' => 'bi-clock-fill', 'permisos' => self::crud('shifts', 'Turnos')],
                    ['titulo' => 'Zonas', 'icono' => 'bi-geo-alt-fill', 'permisos' => self::crud('zones', 'Zonas', extras: [
                        self::extra('zones.map-view', 'Ver Mapa de Zonas'),
                    ])],
                    ['titulo' => 'Feriados', 'icono' => 'bi-flag-fill', 'permisos' => self::crud('holidays', 'Feriados')],
                    ['titulo' => 'Grupos de Personal', 'icono' => 'bi-diagram-3-fill', 'permisos' => self::crud('employeegroups', 'Grupos', [
                        'view' => 'Ver Grupos de Personal',
                    ])],
                    ['titulo' => 'Programación', 'icono' => 'bi-clock-history', 'permisos' => self::crud('scheduling', 'Programación')],
                    ['titulo' => 'Dashboard', 'icono' => 'bi-speedometer2', 'permisos' => [
                        ['codigo' => 'dashboard.view', 'texto' => 'Ver Dashboard', 'tipo' => self::VER],
                    ]],
                ],
            ],
            [
                'clave' => 'cambios', 'titulo' => 'Gestión de Cambios', 'icono' => 'bi-arrow-left-right',
                'recursos' => [
                    ['titulo' => 'Motivos', 'icono' => 'bi-chat-left-text-fill', 'permisos' => self::crud('reasons', 'Motivos')],
                    ['titulo' => 'Cambios de Programación', 'icono' => 'bi-arrow-left-right', 'permisos' => self::crud('scheduling-changes', 'Cambios')],
                ],
            ],
        ];
    }

    /** @return list<string> todos los códigos de permiso */
    public static function codigos(): array
    {
        static $codigos = null;

        return $codigos ??= collect(self::modulos())
            ->flatMap(fn ($m) => collect($m['recursos'])->flatMap(fn ($r) => collect($r['permisos'])->pluck('codigo')))
            ->values()
            ->all();
    }

    public static function existe(string $codigo): bool
    {
        return in_array($codigo, self::codigos(), true);
    }

    /** Recurso del nombre de ruta (admin.<recurso>.<acción>) → prefijo de permiso. */
    private const RECURSOS = [
        'usuarios' => 'users', 'colores' => 'colors', 'marcas' => 'brands', 'modelos' => 'brandmodels',
        'tiposvehiculo' => 'vehicletypes', 'vehiculos' => 'vehicles', 'tipospersonal' => 'employeetypes',
        'personal' => 'employees', 'contratos' => 'contracts', 'asistencias' => 'attendances',
        'vacaciones' => 'vacations', 'turnos' => 'shifts', 'zonas' => 'zones', 'feriados' => 'holidays',
        'grupos' => 'employeegroups', 'programaciones' => 'scheduling', 'motivos' => 'reasons',
        'cambios' => 'scheduling-changes',
    ];

    /** Rutas que no siguen la regla general. Un arreglo significa «cualquiera de estos permisos». */
    private const EXCEPCIONES = [
        'admin.inicio' => null,                                   // el inicio lo ve cualquier usuario con sesión
        'admin.asistencias.marcacion' => 'attendances.create',
        'admin.asistencias.marcacion.estado' => self::CUALQUIERA,   // solo da la hora y el turno del servidor (el reloj del inicio)
        'admin.asistencias.marcar' => 'attendances.create',
        'admin.asistencias.vistaprevia' => ['attendances.create', 'attendances.edit'],
        'admin.cambios.validar' => 'scheduling-changes.create',
        'admin.cambios.detalle' => 'scheduling-changes.view',
        'admin.contratos.estado' => 'contracts.edit',
        'admin.feriados.cargar' => 'holidays.create',
        'admin.feriados.consultar' => ['holidays.create', 'holidays.edit'],
        'admin.personal.contratos' => 'contracts.view',
        'admin.personal.vacaciones' => 'vacations.view',
        'admin.programaciones.dashboard' => 'dashboard.view',
        'admin.programaciones.feriados.quitar' => ['scheduling.create', 'scheduling.edit'],
        'admin.programaciones.grupo' => 'scheduling.view',
        'admin.programaciones.masiva' => 'scheduling.create',
        'admin.programaciones.masiva.guardar' => 'scheduling.create',
        'admin.programaciones.masiva.feriados' => 'scheduling.create',
        'admin.programaciones.masiva.validar' => 'scheduling.create',
        'admin.programaciones.reemplazo' => 'scheduling.edit',
        'admin.programaciones.reemplazo.guardar' => 'scheduling.edit',
        'admin.programaciones.reemplazo.validar' => 'scheduling.edit',
        'admin.programaciones.validar' => ['scheduling.create', 'scheduling.edit'],
        'admin.programaciones.validar.cambio' => 'scheduling.edit',
        'admin.programaciones.candidatos' => ['scheduling.view', 'scheduling.edit'],
        'admin.programaciones.finalizar' => 'scheduling.edit',
        'admin.programaciones.historial' => 'scheduling.view',
        'admin.vacaciones.aprobar' => 'vacations.edit',
        'admin.vacaciones.rechazar' => 'vacations.edit',
        'admin.vehiculos.imagenes' => 'vehicles.manage-images',
        'admin.vehiculos.imagenes.guardar' => 'vehicles.manage-images',
        'admin.zonas.mapa' => 'zones.map-view',
        'admin.zonas.direccion' => ['zones.create', 'zones.edit'],
        'admin.zonas.verificar' => ['zones.create', 'zones.edit'],
        'admin.usuarios.permisos' => 'users.manage',
        'admin.usuarios.permisos.guardar' => 'users.manage',
        'admin.bitacora.index' => 'users.manage',
    ];

    /**
     * Permiso que exige una ruta: null = sin restricción, '*' = cualquier usuario con al menos un permiso,
     * texto = ese permiso, arreglo = cualquiera de esos permisos.
     */
    public static function paraRuta(?string $nombre): string|array|null
    {
        if ($nombre === null || ! str_starts_with($nombre, 'admin.')) {
            return null;
        }

        if (array_key_exists($nombre, self::EXCEPCIONES)) {
            return self::EXCEPCIONES[$nombre];
        }

        $partes = explode('.', $nombre);
        $accion = array_pop($partes);
        $recurso = self::RECURSOS[$partes[1] ?? ''] ?? null;

        if ($recurso === null) {
            return null;
        }

        return match ($accion) {
            'index', 'show' => "$recurso.view",
            'create', 'store' => "$recurso.create",
            'edit', 'update' => "$recurso.edit",
            'destroy' => "$recurso.delete",
            'buscar', 'verificar', 'plantilla' => self::CUALQUIERA,
            default => "$recurso.view",
        };
    }
}
